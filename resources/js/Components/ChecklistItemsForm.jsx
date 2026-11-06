import { useMutation } from "@/Hooks/useMutation";
import { formatTimestamp } from "@/Utils/formatISOTimestampToDate";
import clsx from "clsx";
import {
	startTransition,
	useEffect,
	useMemo,
	useState,
	useTransition,
} from "react";
import { useFieldArray, useForm } from "react-hook-form";
import toast from "react-hot-toast";
import { FaCheckCircle, FaChevronDown, FaChevronRight } from "react-icons/fa";
import { GoAlert } from "react-icons/go";
import { TbAlertCircle } from "react-icons/tb";
import Modal from "@/Components/Modal";
const THREAD_COLORS = ["#6c8ebf", "#82b366", "#d6b656", "#ae4132", "#9673a6"];

// ─── Collapsible Section ─────────────────────────────────────────────────────
const confirmModalID = "perform-checklist-confirm-modal";
const EMPTY_OVERDUE = {};
const noop = () => {};
const isBlank = (v) => String(v ?? "").trim() === "";

const buildRows = (items, periods) => [
	...items.map((item) => ({
		checklist_item_id: item.id, item_status: "", remarks: "",
		period_start: null, period_end: null,
	})),
	...periods.map((p) => ({
		checklist_item_id: p.item_id, item_status: "", remarks: "",
		period_start: p.period_start, period_end: p.period_end,
	})),
];

function CollapsibleSection({
	title,
	defaultOpen = true,
	badge,
	badgeClass,
	children,
	depth = 0,
}) {
	const [open, setOpen] = useState(defaultOpen);
	const color = THREAD_COLORS[(depth - 1) % THREAD_COLORS.length];
	const [isPending, startTransition] = useTransition();
	const toggle = () => startTransition(() => setOpen((v) => !v));

	return (
		<div className="mb-2 flex">
			{depth > 0 && (
				<div
					className="flex flex-col flex-shrink-0 cursor-pointer group"
					style={{ width: 16 }}
					onClick={toggle}
				>
					{/* L-shaped top: vertical down + horizontal right hook */}
					<div
						className="ml-[6px] group-hover:opacity-70 border-b border-l border-base-content transition-opacity"
						style={{
							height: 11,
							borderBottomLeftRadius: 4,
							opacity: 0.35,
						}}
					/>
					{/* Vertical continuation through children */}
					{open && (
						<div
							className="ml-[6px] flex-1 group-hover:opacity-70 border-l transition-opacity"
							style={{
								opacity: 0.35,
							}}
						/>
					)}
				</div>
			)}

			<div className="flex-1">
				<button
					type="button"
					onClick={toggle}
					className="flex items-center mr-1 w-full text-left hover:bg-base-200 rounded transition-colors"
				>
					{isPending ? (
						<span className="w-4 h-4 loading loading-spinner loading-xs" />
					) : open ? (
						<FaChevronDown className="w-4 h-4 opacity-50" />
					) : (
						<FaChevronRight className="w-4 h-4 opacity-50" />
					)}
					<span className="font-semibold text-sm">{title}</span>
					{badge != null && (
						<span className={clsx("ml-1 badge badge-sm", badgeClass)}>
							{badge}
						</span>
					)}
				</button>

				{open && <div>{children}</div>}
			</div>
		</div>
	);
}

// ─── Item Row ─────────────────────────────────────────────────────────────────

function ItemRow({
	index,
	item,
	isOverdue = false,
	periodStart = null,
	periodEnd = null,
	field,
	register,
	setValue,
	watchItems,
	errors,
	gridClass,
}) {
	const inputType = item.input_type;
	const allowedValues = item.allowed_values;
	const checkedAt = item.checked_at;
	const createdBy = item.created_by;
	const verifiedBy = item.verified_by;
	const hasNoSchedule = Boolean(item.is_no_schedule);
	const isDue = item.is_due && !hasNoSchedule;

	return (
		<div
			className={clsx(gridClass, {
				"bg-base-200": index % 2 === 0,
				"bg-error/5": isOverdue,
			})}
			key={field.id}
		>
			<label className="flex flex-col py-1">
				<span>{item.name}</span>
				{isOverdue && (
					<span className="text-xs text-error/70">
						{formatTimestamp(periodStart)} — {formatTimestamp(periodEnd)}
					</span>
				)}
			</label>

			<div>
				<span>{item.criteria}</span>
			</div>

			{/* Hidden fields */}
			<input type="hidden" {...register(`items.${index}.checklist_item_id`)} />
			<input type="hidden" {...register(`items.${index}.period_start`)} />
			<input type="hidden" {...register(`items.${index}.period_end`)} />

			{/* Status input */}
			{inputType === "select" ? (
				(() => {
					const popoverId = `popover-item-${index}`;
					const anchorName = `--anchor-item-${index}`;
					const watchedStatus = watchItems[index]?.item_status;

					return (
						<div className="relative">
							<button
								type="button"
								popoverTarget={popoverId}
								style={{ anchorName }}
								className="btn btn-sm border-0 w-full text-left"
							>
								{watchedStatus || <span className="opacity-40">Select...</span>}
							</button>
							<ul
								id={popoverId}
								popover="auto"
								style={{ positionAnchor: anchorName }}
								className="dropdown menu rounded-box bg-base-100 shadow-sm z-50 not-[&:popover-open]:hidden"
							>
								{(Array.isArray(allowedValues)
									? allowedValues
									: JSON.parse(allowedValues || "[]")
								).map((val) => (
									<li key={val}>
										<a
											onClick={() => {
												setValue(`items.${index}.item_status`, val);
												document.getElementById(popoverId)?.hidePopover?.();
											}}
										>
											{val}
										</a>
									</li>
								))}
							</ul>
						</div>
					);
				})()
			) : (
				<input
					className="input bg-transparent"
					type={inputType === "number" ? "number" : "text"}
					step={inputType === "number" ? "any" : undefined}
					{...register(`items.${index}.item_status`)}
				/>
			)}

			<input
				className="input bg-transparent"
				{...register(`items.${index}.remarks`)}
			/>

			<div className="flex flex-col text-[10px]">
				<div className="flex gap-1 justify-end">
					<div className="opacity-50">{formatTimestamp(checkedAt)}</div>
					{isOverdue ? (
						<span className="text-error font-semibold">overdue</span>
					) : (
						<div>
							{!!isDue && <span className="text-yellow-600">due</span>}
							{!isDue && !!hasNoSchedule && (
								<span className="opacity-50">no schedule</span>
							)}
							{!isDue && !hasNoSchedule && (
								<FaCheckCircle className="text-green-600" />
							)}
						</div>
					)}
					{errors.items?.[index]?.item_status && (
						<p className="text-warning">
							{errors.items[index]?.item_status.message}
						</p>
					)}
					{errors.items?.[index]?.remarks && (
						<p className="text-warning">
							{errors.items[index]?.remarks.message}
						</p>
					)}
				</div>
				<div className="text-right opacity-75 flex justify-end gap-1">
					<span>{createdBy?.FIRSTNAME || "unknown"}</span>
					<span className="opacity-50">|</span>
					<span>{verifiedBy?.FIRSTNAME || "unknown"}</span>
				</div>
			</div>
		</div>
	);
}

// ─── Main Form ───────────────────────────────────────────────────────────────

export default function ChecklistItemsForm({
	assetId,
	checklist,
	items,
	overdueItems = {},
	isItemsLoading,
	onValid = () => {},
	onAnyFilled = () => {},
	onSubmit: onSubmitProp,
}) {
	const { mutate, isLoading, errorMessage } = useMutation();

	const allOverduePeriods = useMemo(
		() => Object.values(overdueItems || {}).flat(),
		[overdueItems],
	);

	const {
		register, control, handleSubmit, setValue, watch,
		formState: { errors },
	} = useForm({
		defaultValues: { items: buildRows(items, allOverduePeriods), notes: "" },
	});

	const { fields } = useFieldArray({ control, name: "items" });
	const watchItems = watch("items");

	// item.id → field index (due items only, indices 0..items.length-1)
	const itemIndexById = useMemo(
		() => Object.fromEntries(items.map((item, i) => [item.id, i])),
		[items],
	);

	// period key → field index (overdue periods start at items.length)
	const overdueIndexByKey = useMemo(() => {
		const map = {};
		allOverduePeriods.forEach((period, i) => {
			const key = `${period.item_id}-${period.period_start}`;
			map[key] = items.length + i;
		});
		return map;
	}, [allOverduePeriods, items.length]);

	// Group due items by schedule_name
	const groupedDueItems = useMemo(() => {
		const groups = {};
		for (const item of items) {
			const key = item.schedule_name ?? "No Schedule";
			if (!groups[key]) groups[key] = [];
			groups[key].push(item);
		}
		return groups;
	}, [items]);

	// Group overdue periods by schedule_name
	const groupedOverdueItems = useMemo(() => {
		const groups = {};
		for (const period of allOverduePeriods) {
			const key = period.schedule_name ?? "No Schedule";
			if (!groups[key]) groups[key] = [];
			groups[key].push(period);
		}
		return groups;
	}, [allOverduePeriods]);

	const rowMeta = useMemo(
		() => [
			...items.map((item) => ({
				label: item.name,
				overdue: false,
				required: Boolean(item.is_due) && !item.is_no_schedule,
			})),
			...allOverduePeriods.map((p) => ({
				label: `${p.name} (${formatTimestamp(p.period_start)} — ${formatTimestamp(p.period_end)})`,
				overdue: true,
				required: true,
			})),
		],
		[items, allOverduePeriods],
	);

	const summary = { answered: [], missing: [], remarksOnly: [] };
	watchItems.forEach((row, i) => {
		const meta = rowMeta[i];
		if (!meta) return;
		if (!isBlank(row?.item_status)) summary.answered.push(meta);
		else if (!isBlank(row?.remarks)) summary.remarksOnly.push(meta);
		else if (meta.required) summary.missing.push(meta);
	});

	const hasOverdue = allOverduePeriods.length > 0;
	const totalOverdueCount = allOverduePeriods.length;
	
	const hasAnyValue = summary.answered.length > 0;
	useEffect(() => { onAnyFilled(hasAnyValue); }, [hasAnyValue]);

	const isAllDone = items.every((item) => item.is_due === 0);

	useEffect(() => {
		onAnyFilled(hasAnyValue);
	}, [hasAnyValue]);

	const onSubmit = async (data) => {
		try {
			await mutate(route("api.checklist-item-result.recordResult"), {
				body: {
					asset_id: assetId,
					checklist_id: checklist?.id,
					notes: data.notes,
					items: data.items,
				},
			});
			if (typeof onSubmitProp === "function") onSubmitProp();
			toast.success("Checklist submitted successfully!");
		} catch (error) {
			console.error(error);
			toast.error(error?.message);
		}
	};

	const openConfirm = () => document.getElementById(confirmModalID)?.showModal();
	const closeConfirm = () => document.getElementById(confirmModalID)?.close();
	const confirmAndSubmit = async () => {
		await handleSubmit(onSubmit)();
		closeConfirm();
	};

	const blockEnter = (e) => {
		if (e.key === "Enter" && e.target.tagName === "INPUT") e.preventDefault();
	};

	const gridClass = "grid grid-cols-[4fr_2fr_2fr_2fr_2fr] items-center";
	const sharedRowProps = { register, setValue, watchItems, errors, gridClass };

	return (
		<>
			<form
				onSubmit={handleSubmit(onSubmit)}
				className="flex-1 flex flex-col justify-between overflow-hidden"
			>
				{isAllDone && (
					<div
						role="alert"
						className="text-success-content bg-success mt-2 p-1 alert alert-outline alert-success"
					>
						<TbAlertCircle className="w-6 h-6" />
						All Items on this checklist are already done
					</div>
				)}

				<div className="overflow-y-auto mt-2 min-h-0 flex-1">
					{/* Column headers */}
					{/* <div className={clsx(gridClass, "mt-4 mb-2 font-semibold")}>
						<div>Items to be checked</div>
						<div>Criteria</div>
						<div>Status</div>
						<div>Remarks</div>
						<div className="opacity-50 text-right">Checked Status</div>
					</div> */}
					<div className="ring-2 ring-error/50 mb-2">
						{/* ── Overdue section ── */}
						{hasOverdue && (
							<CollapsibleSection
								title="Overdue — Appeal"
								badge={totalOverdueCount}
								badgeClass="badge-error"
								defaultOpen={true}
							>
								{Object.entries(groupedOverdueItems).map(
									([scheduleName, periods]) => (
										<CollapsibleSection
											key={scheduleName}
											title={scheduleName}
											badge={periods.length}
											badgeClass="badge-error badge-outline"
											defaultOpen={false}
											depth={1}
										>
											{periods.map((period) => {
												const key = `${period.item_id}-${period.period_start}`;
												const index = overdueIndexByKey[key];
												const field = fields[index];

												if (!field) return null;

												const item = {
													id: period.item_id,
													name: period.name,
													input_type: period.input_type,
													allowed_values: period.allowed_values,
													criteria: period.criteria,
													checked_at: period.checked_at,
													created_by: period.created_by,
													verified_by: period.verified_by,
													is_no_schedule: period.is_no_schedule,
													is_due: 0,
												};

												return (
													<ItemRow
														key={key}
														index={index}
														item={item}
														isOverdue={true}
														periodStart={period.period_start}
														periodEnd={period.period_end}
														field={field}
														{...sharedRowProps}
													/>
												);
											})}
										</CollapsibleSection>
									),
								)}
							</CollapsibleSection>
						)}
					</div>

					{/* ── Due items grouped by schedule ── */}
					{Object.entries(groupedDueItems).map(
						([scheduleName, scheduleItems]) => (
							<CollapsibleSection
								key={scheduleName}
								title={scheduleName}
								defaultOpen={true}
							>
								{scheduleItems.map((item) => {
									const index = itemIndexById[item.id];
									const field = fields[index];

									if (!field) return null;

									return (
										<ItemRow
											key={field.id}
											index={index}
											item={item}
											isOverdue={false}
											field={field}
											{...sharedRowProps}
										/>
									);
								})}
							</CollapsibleSection>
						),
					)}
				</div>

				<div className="flex flex-row w-full shrink-0">
					{checklist?.instruction && (
						<div className="w-2/5 shrink-0 p-2">
							<div className="font-semibold">Instruction</div>
							<div className="mt-2">{checklist?.instruction}</div>
						</div>
					)}
					<div className="w-2/5 shrink-0 p-2">
						<div className="font-semibold">Remarks</div>
						<textarea
							className="mt-2 textarea w-full"
							{...register("notes", { maxLength: { value: 500, message: "Maximum 500 characters" } })}
						/>
						{errors.notes && <p className="text-warning text-xs">{errors.notes.message}</p>}
						{errorMessage && (
							<div className="text-error p-1 border border-error mt-1">
								<GoAlert /> {errorMessage}
							</div>
						)}
					</div>
					<div className="w-1/5 p-2">
						<button
							className="btn btn-primary mt-4 w-full"
							type="button"
							onClick={openConfirm}
							disabled={isLoading || (summary.answered.length === 0 && summary.remarksOnly.length === 0)}
						>
							Submit
						</button>
					</div>
				</div>
			</form>

			<Modal id={confirmModalID} title="Review before submitting">
				<div className="flex flex-col gap-3 text-sm">
					<p>
						<span className="font-semibold">{summary.answered.length}</span> item(s) will be
						submitted for <span className="font-semibold">{checklist?.name}</span>.
					</p>

					{summary.remarksOnly.length > 0 && (
						<div role="alert" className="alert alert-error alert-soft flex-col items-start">
							<span className="font-semibold">
								{summary.remarksOnly.length} item(s) have remarks but no status. These would be
								discarded. Go back and fill in the status.
							</span>
							<ul className="list-disc pl-5 max-h-32 overflow-y-auto">
								{summary.remarksOnly.map((m, i) => <li key={i}>{m.label}</li>)}
							</ul>
						</div>
					)}

					{summary.missing.length > 0 && (
						<div role="alert" className="alert alert-warning alert-soft flex-col items-start">
							<span className="font-semibold">
								You skipped {summary.missing.length} item(s). They will stay due / overdue.
							</span>
							<ul className="list-disc pl-5 max-h-40 overflow-y-auto">
								{summary.missing.map((m, i) => (
									<li key={i}>
										{m.label}
										{m.overdue && <span className="badge badge-xs badge-error ml-1">overdue</span>}
									</li>
								))}
							</ul>
						</div>
					)}
				</div>

				<div className="flex justify-end gap-2 mt-4">
					<button type="button" className="btn" onClick={closeConfirm}>Go back</button>
					<button
						type="button"
						className="btn btn-primary"
						onClick={confirmAndSubmit}
						disabled={isLoading || summary.remarksOnly.length > 0 || summary.answered.length === 0}
					>
						Confirm &amp; submit
					</button>
				</div>
			</Modal>
		</>
	);
}
