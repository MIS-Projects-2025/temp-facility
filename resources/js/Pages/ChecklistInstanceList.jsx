import CancellableActionButton from "@/Components/CancellableActionButton";
import SmartCalendarContainer from "@/Components/DatePicker";
import Modal from "@/Components/Modal";
import MultiSelectSearchableDropdown from "@/Components/MultiSelectSearchableDropdown";
import Pagination from "@/Components/Pagination";
import { createClickableCell } from "@/Components/tanStackTable/ClickableCell";
import TanstackTable from "@/Components/tanStackTable/TanstackTable";
import { useEditableTable } from "@/Hooks/useEditableTable";
import { useMutation } from "@/Hooks/useMutation";
import formatDateTime from "@/Utils/formatDateTime";
import formatFriendlyDate from "@/Utils/formatFriendlyDate";
import { formatTimestamp } from "@/Utils/formatISOTimestampToDate";
import formatPastDateTimeLabel from "@/Utils/formatPastDateTimeLabel";
import { router, usePage } from "@inertiajs/react";
import clsx from "clsx";
import React, { useEffect, useRef, useState } from "react";
import toast from "react-hot-toast";
import { MdOutlinePending, MdVerified } from "react-icons/md";

const reviewResultsModalID = "checklist-instance-results-modal";
const checklistModalID = "checklist-instance-list-checklist-modal";
const maxItem = 30; // all

function ChecklistInstanceList() {
	const {
		checklistInstance: serverChecklistInstance,
		verified: serverVerified,
		createdAtStart: serverCreatedAtStart,
		createdAtEnd: serverCreatedAtEnd,
		checklistIds: serverChecklistId,
		checklists: serverChecklists,
		perPage: serverPerPage,
		totalEntries,
	} = usePage().props;

	console.log(
		"🚀 ~ ChecklistInstanceList ~ serverChecklistId:",
		serverChecklistId,
		console.log(
			"🚀 ~ ChecklistInstanceList ~ serverChecklists:",
			serverChecklists,
		),
	);

	const {
		mutate,
		isLoading: isMutateLoading,
		errorMessage: mutateErrorMessage,
		errorData: mutateErrorData,
		cancel: mutateCancel,
	} = useMutation();

	const [selectedInstance, setSelectedInstance] = useState([]);
	const [selectedChecklist, setSelectedChecklist] = React.useState(
		serverChecklistId?.map((checklistId) => Number(checklistId)),
	);

	const defaultChecklistNames =
		serverChecklists
			?.filter((checklist) => selectedChecklist.includes(checklist.id))
			.map((checklist) => checklist.name) || [];

	console.log(
		"🚀 ~ handleEditedItemClick ~ defaultChecklistNames:",
		defaultChecklistNames,
	);

	const [filterVerified, setFilterVerified] = useState(serverVerified || false);
	const [filterCreateDateStart, setFilterCreateDateStart] =
		useState(serverCreatedAtStart);
	const [filterCreateDateEnd, setFilterCreateDateEnd] =
		useState(serverCreatedAtEnd);

	console.log(
		"🚀 ~ ChecklistInstanceList ~ selectedChecklist:",
		selectedChecklist,
	);

	const refresh = () => {
		router.reload({
			data: {
				verified: filterVerified,
				createdAtStart: formatDateTime(filterCreateDateStart),
				createdAtEnd: formatDateTime(filterCreateDateEnd),
				checklistIds: selectedChecklist.join(","),
				perPage: maxItem,
			},
			preserveState: true,
			preserveScroll: true,
		});
	};

	function handleEditedItemClick(row, value, column) {
		console.log("🚀 ~ handleEditedItemClick ~ row:", row);
		// const rootKey = column?.columnDef?.accessorKey?.split(".")[0];
		// setSelectedCell({ rootKey, row, value, column });
		// setSelectedEditItem([value]);
		setSelectedInstance(row.original);
	}

	const columns = React.useMemo(
		() => [
			{
				header: "Checklist",
				columns: [
					{
						header: "Checklist",
						accessorKey: "checklist",
						cell: ({ row }) => {
							console.log("🚀 ~ ChecklistInstanceList ~ row:", row);
							const submission_type = row.original?.submission_type;
							const checklist = row.original.checklist;
							const isVerified = row.original.verified_at !== null;

							return (
								<div
									className={`justify-center flex flex-col items-left ${submission_type === "late" ? "bg-linear-to-r from-red-500/10 to-transparent" : ""}`}
								>
									<div className="flex gap-1">
										{isVerified ? (
											<MdVerified className="w-4 h-4 text-green-500" />
										) : (
											<MdOutlinePending className="w-4 h-4 text-yellow-500" />
										)}
										<span>{checklist.name}</span>
									</div>
									<div className="text-xs opacity-75 flex gap-1">
										<span>{checklist.form_control_no}</span>
										<span></span>
									</div>
								</div>
							);
						},
						size: 400,
					},
				],
			},
			{
				header: "Results (click for more details)",
				accessorFn: (row) => {
					if (!row.results || row.results.length === 0) return "No results";
					const late_results_count = row?.late_results_count;

					return `review (${row.results.length}) item results`;
				},
				size: 250,
				cell: createClickableCell({
					modalID: reviewResultsModalID,
					handleCellClick: handleEditedItemClick,
					isEditable: false,
					formatDisplayValue: ({ row }) => {
						const {
							results,
							late_results_count,
							is_approver,
							can_approve_now,
						} = row.original;

						if (!results || results.length === 0) return null;

						const hasLate = late_results_count > 0;

						return (
							<div className="gap-1 flex justify-between items-center">
								<div className="flex flex-col">
									<div className="w-18 flex justify-between">
										<span>item{results.length !== 1 ? "s" : ""}</span>
										<span>{results.length}</span>
									</div>
									{hasLate && (
										<div className="flex text-red-600 justify-between w-18">
											<div>late</div>
											<div className="flex justify-between">
												<span>{late_results_count}</span>
											</div>
										</div>
									)}
								</div>
								{can_approve_now && (
									<div className="badge badge-xs badge-warning animate-pulse">
										needs your approval
									</div>
								)}
								{is_approver && !can_approve_now && (
									<div className="badge badge-xs badge-ghost opacity-50">
										approval pending
									</div>
								)}
							</div>
						);
					},
				}),
			},
			{
				header: "Verified",
				accessorKey: "verified_at",
				cell: ({ row }) => {
					const {
						verified_at,
						verified_by,
						verifier,
						approved_at,
						approved_by,
						approver,
						submission_type,
					} = row.original;

					const isLate = submission_type === "late";
					const isResolved = isLate ? !!approved_at : !!verified_at;

					const displayBy = isLate ? approved_by : verified_by;
					const displayAt = isLate ? approved_at : verified_at;
					const displayName = isLate
						? approver
							? `${approver.FIRSTNAME} ${approver.LASTNAME}`
							: "unknown"
						: verifier
							? `${verifier.FIRSTNAME} ${verifier.LASTNAME}`
							: "unknown";

					return (
						<div className="flex flex-col px-1 gap-1 text-left w-full">
							<div className="text-xs flex items-center gap-1">
								{isLate && (
									<span className="badge badge-xs badge-warning">appeal</span>
								)}
								by:
								{displayBy ? (
									<span className="ml-1 badge badge-xs badge-soft badge-primary">
										{displayName} ({displayBy})
									</span>
								) : (
									<span className="ml-1 opacity-50 text-xs">N/A</span>
								)}
							</div>
							<div className={clsx("text-xs", { "badge-success": isResolved })}>
								{formatPastDateTimeLabel(displayAt)}
								<span className="ml-1 opacity-50">
									{displayAt ? formatTimestamp(displayAt) : "pending"}
								</span>
							</div>
							<div
								className={clsx(
									"absolute -right-1 top-0 w-1 h-full",
									isResolved
										? "bg-success"
										: isLate
											? "bg-error"
											: "bg-warning",
								)}
							/>
						</div>
					);
				},
				size: 150,
			},
			{
				header: "Performed",
				accessorKey: "created_at", // or row.original
				cell: ({ row }) => {
					const { created_at, created_by, creator, submission_type } =
						row.original;
					console.log("🚀 ~ ChecklistInstanceList ~ created_at:", created_at);

					const creatorName = creator
						? `${creator.FIRSTNAME} ${creator.LASTNAME}`
						: "unknown";

					return (
						<div
							className={`flex flex-col px-1 gap-1 w-full relative ${submission_type === "late" ? "bg-linear-to-l from-red-500/10 to-transparent" : ""}`}
						>
							<div className="text-xs">
								<div className="text-xs">
									by:
									{created_by ? (
										<span className="ml-1 badge badge-xs badge-soft badge-primary">
											{creatorName} ({created_by})
										</span>
									) : (
										<span className="ml-1 opacity-50 text-xs">N/A</span>
									)}
								</div>
							</div>
							<div className="text-xs w-full badge-secondary">
								{formatPastDateTimeLabel(created_at)}
								<span className="ml-1 opacity-50">
									{created_at
										? `${formatTimestamp(created_at)}`
										: "Not checked"}
								</span>
							</div>
						</div>
					);
				},
				size: 250,
			},
		],
		[],
	);

	const { table } = useEditableTable(
		serverChecklistInstance.data || [],
		columns,
		{
			isMultipleSelection: true,
		},
	);

	const handleApprove = async (instanceId) => {
		if (!instanceId) {
			toast.error("No instance selected.");
			return;
		}

		try {
			await mutate(
				route("api.checklist-instance.approve", { id: instanceId }),
				{
					method: "PATCH",
					body: { remarks: null },
				},
			);

			toast.success("Checklist approved successfully!");
			document.getElementById(reviewResultsModalID).close();
			refresh();
		} catch (error) {
			toast.error(error?.message);
			console.error(error);
		}
	};

	const handleVerify = async (
		instances = Object.keys(table.getState().rowSelection),
	) => {
		if (instances.length === 0) {
			alert("No selected rows to verify.");
			return;
		}

		try {
			await mutate(route("api.checklist-instance.verify"), {
				method: "PATCH",
				body: instances,
			});

			toast.success("Checklist verified successfully!");
			table.resetRowSelection();
			document.getElementById(reviewResultsModalID).close();
			refresh();
		} catch (error) {
			toast.error(error?.message);
			console.error(error);
		}
	};

	const preprocessResults = (results) => {
		if (!results || results.length === 0) return [];

		const grouped = [];
		let currentAssetCode = null;
		let spanCount = 0;

		// Count consecutive repeats
		results.forEach((r, i) => {
			if (r.asset?.code === currentAssetCode) {
				spanCount++;
			} else {
				// Assign rowSpan to previous group
				if (spanCount > 0) {
					grouped[grouped.length - spanCount].rowSpan = spanCount;
				}
				currentAssetCode = r.asset?.code;
				spanCount = 1;
			}
			grouped.push({ ...r, rowSpan: 0 }); // temp rowSpan
		});

		// Assign rowSpan to last group
		if (spanCount > 0) {
			grouped[grouped.length - spanCount].rowSpan = spanCount;
		}

		return grouped;
	};

	const processed = preprocessResults(selectedInstance.results);

	const isMounted = useRef(false);

	useEffect(() => {
		if (!isMounted.current) {
			isMounted.current = true;
			return;
		}

		router.reload({
			data: {
				verified: filterVerified,
				createdAtStart: formatDateTime(filterCreateDateStart),
				createdAtEnd: formatDateTime(filterCreateDateEnd),
				checklistIds: selectedChecklist.join(","),
				perPage: serverPerPage,
			},
			preserveState: true,
			preserveScroll: true,
		});
	}, [
		filterVerified,
		filterCreateDateStart,
		filterCreateDateEnd,
		selectedChecklist,
	]);

	const goToPage = (page) => {
		router.reload({
			data: {
				perPage: maxItem,
				page,
			},
			preserveState: true,
			preserveScroll: true,
		});
	};

	const handleDateFilterChange = (dates) => {
		const [start, end] = dates;
		setFilterCreateDateStart(start);
		setFilterCreateDateEnd(end);
	};

	return (
		<div>
			<h1 className="text-lg font-semibold">Performed Checklist List</h1>
			<div className="flex">
				<fieldset className="fieldset bg-base-100 border-base-300 rounded-box w-64 border p-4">
					<legend className="fieldset-legend">Filter Verified</legend>
					<label className="label">
						<input
							type="checkbox"
							checked={filterVerified}
							className="toggle border-warning-600 bg-warning-500 checked:border-success-500 checked:bg-success-400 checked:text-success"
							onChange={(e) => setFilterVerified(e.target.checked)}
						/>
						<span className="label-text">
							{filterVerified ? "Verified" : "Unverified"}
						</span>
					</label>
				</fieldset>

				<div className="flex flex-col w-full">
					<MultiSelectSearchableDropdown
						modalId={checklistModalID}
						options={
							serverChecklists?.map((checklist) => ({
								id: checklist.id,
								value: checklist.name,
								label: checklist.form_control_no,
								original: checklist,
							})) || []
						}
						onChange={(value) => {
							setSelectedChecklist(value);
						}}
						returnKey="id"
						defaultSelectedOptions={defaultChecklistNames}
						disableSelectedContainer
						disableTooltip
						itemName="Checklist List"
						prompt="Filter Checklist"
						contentClassName="w-full h-50"
						buttonSelectorClassName="min-h-8 w-full h-auto btn-soft btn-primary text-left"
					/>
					<div>Date performed filter</div>

					<SmartCalendarContainer
						selectedDate={filterCreateDateStart}
						onChange={handleDateFilterChange}
						startDate={filterCreateDateStart}
						endDate={filterCreateDateEnd}
						props={{
							portalId: "root-portal",
							className: "w-120 input",
							swapRange: true,
							selectsRange: true,
							isClearable: true,
							showTimeSelect: true,
							timeIntervals: 15,
							dateFormat: "yyyy-MM-dd HH:mm",
						}}
					/>
				</div>
			</div>
			<div className="flex gap-2 sticky right-0">
				<CancellableActionButton
					abort={mutateCancel}
					refetch={handleVerify}
					loading={isMutateLoading}
					buttonText="verify"
					loadingMessage="Verifying..."
					buttonClassName="btn-accent"
					disabled={Object.keys(table.getState().rowSelection).length === 0}
				/>
			</div>

			<Pagination
				links={serverChecklistInstance?.links}
				currentPage={serverChecklistInstance?.current_page}
				goToPage={goToPage}
				filteredTotal={serverChecklistInstance?.total}
				overallTotal={totalEntries}
				start={serverChecklistInstance?.from}
				end={serverChecklistInstance?.to}
			/>

			<TanstackTable table={table} />

			<Modal
				id={reviewResultsModalID}
				title="Review Checklist"
				className="w-11/12 max-w-7xl"
			>
				<div className="flex justify-between items-center">
					<div>
						{selectedInstance?.checklist?.name} -{" "}
						<span className="text-xs">
							{selectedInstance?.checklist?.form_control_no}
						</span>
					</div>
					<div>
						{selectedInstance?.late_results_count > 0 && (
							<span className="text-error-content bg-error border px-2">
								Subjected To Appeal
							</span>
						)}
					</div>
				</div>
				<div className="flex justify-between items-center">
					<div>
						submitted by {selectedInstance?.creator?.FIRSTNAME}{" "}
						{selectedInstance?.creator?.LASTNAME}
					</div>

					<div className="leading-3 text-xs opacity-75">
						created{" "}
						<span className="font-semibold">
							{formatPastDateTimeLabel(selectedInstance?.created_at)}
						</span>{" "}
						({formatFriendlyDate(selectedInstance?.created_at, true)})
					</div>
				</div>
				<div className="mt-2 overflow-x-auto max-h-160">
					<table className="border border-base-content/10 table table-zebra w-full divide-y">
						<thead className="">
							<tr>
								<th className="px-4 py-2 text-left text-sm">Asset Code</th>
								<th className="px-4 py-2 text-left text-sm">Item Name</th>
								<th className="px-4 py-2 text-left text-sm">Criteria</th>
								<th className="px-4 py-2 text-left text-sm">Status</th>
								<th className="px-4 py-2 text-left text-sm">Remarks</th>
								<th className="px-4 py-2 text-left text-sm">Period</th>
							</tr>
						</thead>
						<tbody className="divide-y">
							{processed?.length > 0 &&
								processed.map((r) => {
									console.log("🚀 ~ ChecklistInstanceList ~ r:", r);

									const isLate =
										selectedInstance?.created_at &&
										r?.period_end &&
										new Date(selectedInstance.created_at) >
											new Date(r.period_end);

									return (
										<tr key={r.id} className="">
											{r.rowSpan > 0 && (
												<td className="px-4 py-2 text-sm" rowSpan={r.rowSpan}>
													<div>
														<span className={r.asset?.code ? "" : "opacity-50"}>
															{r.asset?.code || "N/A"}
														</span>
														{/* <span
															className={`pl-1 text-xs ${r.asset?.location?.location_name ? "" : "opacity-50"}`}
														>
															@ {r.asset?.location?.location_name || "N/A"}
														</span> */}
													</div>
												</td>
											)}
											<td
												className={`px-4 py-2 text-sm ${r.item?.item?.name ? "" : "opacity-50"}`}
											>
												{r.item?.item?.name || "N/A"}
											</td>
											<td
												className={`px-4 py-2 text-sm ${r.item?.criteria ? "" : "opacity-50"}`}
											>
												{r.item?.criteria || "N/A"}
											</td>
											<td
												className={`px-4 py-2 text-sm ${r.item_status ? "" : "opacity-50"}`}
											>
												{r.item_status || "N/A"}
											</td>
											<td
												className={`px-4 py-2 text-sm ${r.remarks ? "" : "opacity-50"}`}
											>
												{r.remarks || "N/A"}
											</td>
											<td
												className={clsx(
													`flex gap-2 items-center px-4 py-2 text-sm`,
													{
														"ring ring-error": isLate,
													},
												)}
											>
												<span>
													{formatTimestamp(r?.period_start || null) || "N/A"}
												</span>
												<span className="opacity-50">to</span>
												<span>
													{formatTimestamp(r?.period_end || null) || "N/A"}
												</span>
												<span>
													{isLate && (
														<span className="font-extrabold text-error ml-1">
															Appeal
														</span>
													)}
												</span>
											</td>
										</tr>
									);
								})}

							{processed?.length === 0 && (
								<tr>
									<td colSpan="5" className="px-4 py-2 text-sm text-center">
										No items
									</td>
								</tr>
							)}
						</tbody>
					</table>
				</div>
				<div className="flex justify-between items-center border-t border-base-content/10 pt-2 mt-2">
					{/* Left: approver chain status for late submissions */}
					{selectedInstance?.submission_type === "late" && (
						<div className="flex flex-col gap-1">
							{Object.entries(
								(selectedInstance?.all_approvers ?? []).reduce((acc, a) => {
									(acc[a.level] ??= []).push(a);
									return acc;
								}, {}),
							).map(([level, approvers]) => {
								const isLevelApproved = approvers.some(
									(a) => a.status === "approved",
								);

								return (
									<div key={level} className="flex items-center text-xs gap-1">
										<span
											className={`badge badge-xs mr-1 w-14 justify-center ${isLevelApproved ? "badge-success" : "badge-warning"}`}
										>
											Level {level}
										</span>
										<span className="opacity-70">
											{isLevelApproved
												? "approved by "
												: approvers.length === 1
													? "awaiting "
													: "awaiting any one of "}
										</span>
										<div className="flex gap-1">
											{approvers.map((a, i) => {
												const isApproved = a.status === "approved";

												if (!isLevelApproved && isApproved) return null; // skip if another approved, redundant

												return (
													<div key={a.user_id} className="">
														<span
															className={`font-semibold ${isApproved ? "text-success" : "opacity-70"}`}
														>
															{a.employee?.FIRSTNAME} {a.employee?.LASTNAME}
														</span>
														<span className="opacity-50 ml-[1px]">
															({a.user_id})
														</span>
														{!isLevelApproved && i < approvers.length - 1 && (
															<span className="opacity-40 mx-1">or</span>
														)}
													</div>
												);
											})}
										</div>
									</div>
								);
							})}
						</div>
					)}
					{/* {selectedInstance?.submission_type === "late" && (
						<div className="flex flex-col gap-1">
							{Object.entries(
								(selectedInstance?.pending_approvers ?? []).reduce((acc, a) => {
									(acc[a.level] ??= []).push(a);
									return acc;
								}, {}),
							).map(([level, approvers]) => (
								<div key={level} className="text-xs opacity-70">
									<span className="badge badge-xs badge-warning mr-1 w-14 justify-center">
										Level {level}
									</span>
									{approvers.length === 1
										? "awaiting "
										: "awaiting any one of "}
									{approvers.map((a, i) => (
										<span key={a.user_id}>
											<span className="font-semibold">
												{a.employee?.FIRSTNAME} {a.employee?.LASTNAME}
											</span>
											<span className="opacity-50 ml-1">({a.user_id})</span>
											{i < approvers.length - 1 && (
												<span className="opacity-40 mx-1">or</span>
											)}
										</span>
									))}
								</div>
							))}
						</div>
					)} */}

					{selectedInstance?.submission_type === "punctual" && <div />}

					{/* Right: action */}
					<div>
						{selectedInstance?.submission_type === "punctual" ? (
							// --- Punctual flow ---
							selectedInstance?.verified_at ? (
								<div>
									<MdVerified className="text-success inline" />
									<span className="text-success mx-1">verified</span>
									{formatPastDateTimeLabel(selectedInstance?.verified_at)} by
									<span className="px-1 text-primary">
										{selectedInstance?.verifier?.FIRSTNAME || "unknown"}
									</span>
									<span>
										({selectedInstance?.verifier?.EMPLOYID || "unknown"})
									</span>
								</div>
							) : (
								<CancellableActionButton
									refetch={() => handleVerify([selectedInstance?.id])}
									loading={isMutateLoading}
									buttonText="Verify"
									buttonClassName="btn-primary"
									abort={mutateCancel}
									loadingMessage="Verifying"
								/>
							)
						) : // --- Late flow ---
						selectedInstance?.approved_at ? (
							<div>
								<MdVerified className="text-success inline" />
								<span className="text-success mx-1">approved</span>
								{formatPastDateTimeLabel(selectedInstance?.approved_at)} by
								<span className="px-1 text-primary">
									{selectedInstance?.approver?.FIRSTNAME || "unknown"}
								</span>
								<span>({selectedInstance?.approved_by || "unknown"})</span>
							</div>
						) : selectedInstance?.can_approve_now ? (
							<CancellableActionButton
								refetch={() => handleApprove(selectedInstance?.id)}
								loading={isMutateLoading}
								buttonText="Approve"
								buttonClassName="btn-warning"
								abort={mutateCancel}
								loadingMessage="Approving"
							/>
						) : selectedInstance?.is_approver ? (
							<span className="text-xs opacity-50">
								{
									{
										level_already_approved:
											"Your level has already been approved.",
										previous_level_pending:
											"Waiting for previous level approval.",
									}[selectedInstance?.deny_reason]
								}
							</span>
						) : (
							<span className="text-xs opacity-50">Pending approval</span>
						)}
					</div>
				</div>
			</Modal>
		</div>
	);
}

export default ChecklistInstanceList;
