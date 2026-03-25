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

const THREAD_COLORS = ["#6c8ebf", "#82b366", "#d6b656", "#ae4132", "#9673a6"];

// ─── Collapsible Section ─────────────────────────────────────────────────────

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
	console.log("🚀 ~ ChecklistItemsForm ~ overdueItems:", overdueItems);
	const { mutate, isLoading, errorMessage } = useMutation();

	const allOverduePeriods = useMemo(
		() => Object.values(overdueItems || {}).flat(),
		[overdueItems],
	);

	const {
		register,
		control,
		handleSubmit,
		setValue,
		watch,
		reset,
		formState: { errors, isValid },
	} = useForm({
		defaultValues: {
			items: [
				...items.map((item) => ({
					checklist_item_id: item.id,
					item_status: "",
					remarks: "",
					period_start: null,
					period_end: null,
				})),
				...allOverduePeriods.map((period) => ({
					checklist_item_id: period.item_id,
					item_status: "",
					remarks: "",
					period_start: period.period_start,
					period_end: period.period_end,
				})),
			],
			notes: "",
		},
	});

	useEffect(() => {
		if (!allOverduePeriods?.length) return;

		reset({
			items: [
				...items.map((item) => ({
					checklist_item_id: item.id,
					item_status: "",
					remarks: "",
					period_start: null,
					period_end: null,
				})),
				...allOverduePeriods.map((period) => ({
					checklist_item_id: period.item_id,
					item_status: "",
					remarks: "",
					period_start: period.period_start,
					period_end: period.period_end,
				})),
			],
			notes: "",
		});
	}, [allOverduePeriods]);

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

	// item_id → item metadata (for rendering overdue rows)
	const itemMetaById = useMemo(
		() => Object.fromEntries(items.map((item) => [item.id, item])),
		[items],
	);

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

	const hasOverdue = allOverduePeriods.length > 0;
	const totalOverdueCount = allOverduePeriods.length;
	const isAllDone = items.every((item) => item.is_due === 0);
	const hasAnyValue = watchItems.some(
		(item) => item.item_status && item.item_status.trim() !== "",
	);

	useEffect(() => {
		onAnyFilled(hasAnyValue);
	}, [hasAnyValue]);
	useEffect(() => {
		onValid(isValid);
	}, [isValid, onValid]);

	const onSubmit = async (data) => {
		console.log("🚀 ~ onSubmit ~ data:", data);
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

	const gridClass = "grid grid-cols-[4fr_2fr_2fr_2fr_2fr] items-center";
	const sharedRowProps = { register, setValue, watchItems, errors, gridClass };

	if (isItemsLoading) {
		return (
			<div className="w-full h-100 skeleton flex justify-center items-center">
				<span className="loading loading-spinner"></span>
			</div>
		);
	}

	return (
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
						{...register("notes", {
							maxLength: { value: 500, message: "Maximum 500 characters" },
						})}
					/>
					{errorMessage && (
						<div className="text-error p-1 border border-error mt-1">
							<GoAlert /> {errorMessage}
						</div>
					)}
				</div>
				<div className="w-1/5 p-2">
					<button
						className="btn btn-primary mt-4 w-full"
						type="submit"
						disabled={isLoading}
					>
						Submit
					</button>
				</div>
			</div>
		</form>
	);
}
