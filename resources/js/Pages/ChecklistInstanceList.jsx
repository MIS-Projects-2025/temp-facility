import Modal from "@/Components/Modal";
import MultiSelectSearchableDropdown from "@/Components/MultiSelectSearchableDropdown";
import CheckBoxColumn from "@/Components/tanStackTable/CheckBoxColumn";
import { createClickableCell } from "@/Components/tanStackTable/ClickableCell";
import TanstackTable from "@/Components/tanStackTable/TanstackTable";
import { useEditableTable } from "@/Hooks/useEditableTable";
import { useMutation } from "@/Hooks/useMutation";
import { useChecklistStore } from "@/Store/checklistStore";
import { formatTimestamp } from "@/Utils/formatISOTimestampToDate";
import { router, usePage } from "@inertiajs/react";
import clsx from "clsx";
import React, { useEffect, useRef, useState } from "react";
import toast from "react-hot-toast";

const reviewResultsModalID = "checklist-instance-results-modal";
const checklistModalID = "checklist-instance-list-checklist-modal";
const maxItem = 30; // all

function ChecklistInstanceList() {
	const { data: checklists, isLoading, fetchChecklists } = useChecklistStore();

	const {
		mutate,
		isLoading: isMutateLoading,
		errorMessage: mutateErrorMessage,
		errorData: mutateErrorData,
		cancel: mutateCancel,
	} = useMutation();

	const [selectedInstance, setSelectedInstance] = useState([]);
	const [selectedCell, setSelectedCell] = useState(null);
	const [selectedChecklist, setSelectedChecklist] = React.useState([]);
	const [filterVerified, setFilterVerified] = useState(false);
	const [filterCreateDate, setFilterCreateDate] = useState(null);

	console.log(
		"🚀 ~ ChecklistInstanceList ~ selectedChecklist:",
		selectedChecklist,
	);

	const refresh = () => {
		router.reload({
			data: {
				verified: filterVerified,
				created_at: filterCreateDate,
				checklist_id: selectedChecklist,
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
							const checklist = row.original.checklist;
							return (
								<div className="justify-center flex flex-col items-left">
									<span>{checklist.name}</span>
									<span className="text-xs">{checklist.form_control_no}</span>
								</div>
							);
						},
						size: 350,
					},
				],
			},
			{
				header: "Results (click for more details)",
				accessorFn: (row) => {
					if (!row.results || row.results.length === 0) return "No results";

					return `review (${row.results.length}) item results`;
				},
				size: 200,
				cell: createClickableCell({
					modalID: reviewResultsModalID,
					handleCellClick: handleEditedItemClick,
					isEditable: false,
				}),
			},
			{
				header: "Status",
				accessorKey: "verified", // can be anything; we'll use row.original
				cell: ({ row }) => {
					const { verified_at, verified_by, created_at, created_by } =
						row.original;

					const notVerified = !verified_at && !verified_by;

					return (
						<div className="flex flex-col gap-2 py-4 w-full h-full text-right mr-2">
							{/* Verified */}
							<div className="flex flex-col items-end place-content-end h-full w-full relative">
								<span
									className={clsx("text-xs", {
										"bg-warning/50": notVerified,
										"badge-success": !notVerified,
									})}
								>
									{verified_at ? formatTimestamp(verified_at) : "pending"}
								</span>
								<div className="text-xs">
									verified by:
									<span className="min-w-10 ml-1 badge badge-xs badge-soft badge-primary">
										{verified_by || "N/A"}
									</span>
								</div>
								{/* Right rectangle */}
							</div>
							<div
								className={clsx(
									"absolute -right-1 top-0 w-1 h-full",
									notVerified ? "bg-warning" : "bg-success",
								)}
							/>

							{/* Checked */}
							<div className="place-content-start items-end flex flex-col h-full relative">
								<span className="text-xs w-full badge-secondary">
									{created_at ? formatTimestamp(created_at) : "Not checked"}
								</span>
								<div className="text-xs">
									performed by:
									<span className="min-w-10 ml-1 badge badge-xs badge-soft badge-primary">
										{created_by || "N/A"}
									</span>
								</div>
							</div>
						</div>
					);
				},
				size: 200, // adjust width as needed
			},

			// {
			//     header: "Audit Info",
			//     columns: [
			//         ReadOnlyColumns({
			//             accessorKey: "created_at",
			//             header: "Created At",
			//             options: { size: 140 },
			//         }),
			//         ReadOnlyColumns({
			//             accessorKey: "created_by",
			//             header: "Created By",
			//             options: { size: 140 },
			//         }),
			//         ReadOnlyColumns({
			//             accessorKey: "modified_by",
			//             header: "Modified By",
			//         }),
			//         ReadOnlyColumns({
			//             accessorKey: "modified_at",
			//             header: "Modified At",
			//             options: { size: 160 },
			//         }),
			//     ],
			// },
		],
		[],
	);

	const {
		checklist_instance: serverChecklistInstance,
		// verified:
		// created_at:
		// checklist_id:
		// perPage:
		// totalEntries:
	} = usePage().props;

	const { table } = useEditableTable(
		serverChecklistInstance.data || [],
		columns,
		{
			isMultipleSelection: true,
		},
	);

	const handleVerify = async () => {
		if (Object.keys(table.getState().rowSelection).length === 0) {
			alert("No selected rows to verify.");
			return;
		}

		// if (!confirm("Are you sure you want to discard all changes?")) return;
		try {
			await mutate(route("api.checklist-instance.verify"), {
				method: "PATCH",
				body: Object.keys(table.getState().rowSelection),
			});
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
	console.log("🚀 ~ ChecklistInstanceList ~ processed:", processed);

	return (
		<div>
			{/* <pre>{JSON.stringify(table.getState().rowSelection, null, 2)}</pre>( */}
			{/* {data.length} rows) */}
			<h1 className="text-lg font-semibold">Performed Checklist List</h1>
			<MultiSelectSearchableDropdown
				modalId={checklistModalID}
				options={
					checklists?.checklistArray?.map((checklist) => ({
						id: checklist.id,
						value: checklist.name,
						label: checklist.form_control_no,
						original: checklist,
					})) || []
				}
				onChange={(value) => {
					console.log("🚀 ~ ChecklistList ~ value:", value);
					setSelectedChecklist(value);
				}}
				returnKey="id"
				defaultSelectedOptions={selectedChecklist}
				// controlledSelectedOptions={selectedChecklist}
				// customButtonLabel={({ selectedOptions }) => {
				//     return (
				//         <div>
				//             {selectedOptions.length > 0 ? (
				//                 <div className="flex items-center justify-between w-full">
				//                     <h1 className="flex gap-2 items-center w-full text-lg">
				//                         <span className="text-sm font-normal text-base-content">
				//                             Performing
				//                         </span>
				//                         <span>{selectedOptions[0]}</span>
				//                     </h1>
				//                 </div>
				//             ) : (
				//                 "Select a checklist to perform"
				//             )}
				//         </div>
				//     );
				// }}
				disableSelectedContainer
				disableTooltip
				isLoading={isLoading}
				itemName="Checklist List"
				prompt="Select Checklist"
				contentClassName="h-50"
				buttonSelectorClassName="min-h-8 w-full h-auto btn-soft btn-primary text-left"
			/>
			<div className="flex gap-2 sticky right-0">
				<button
					type="button"
					className="btn btn-accent"
					onClick={handleVerify}
					disabled={Object.keys(table.getState().rowSelection).length === 0}
				>
					Verify
				</button>
			</div>
			<TanstackTable table={table} />

			<Modal
				id={reviewResultsModalID}
				title="Review Checklist Instance Results"
				className="w-11/12 max-w-6xl"
			>
				<div className="mt-2 overflow-x-auto">
					<table className="border border-base-content/10 table table-zebra w-full divide-y">
						<thead className="">
							<tr>
								<th className="px-4 py-2 text-left text-sm">Asset Code</th>
								<th className="px-4 py-2 text-left text-sm">Item Name</th>
								<th className="px-4 py-2 text-left text-sm">Criteria</th>
								<th className="px-4 py-2 text-left text-sm">Status</th>
								<th className="px-4 py-2 text-left text-sm">Remarks</th>
							</tr>
						</thead>
						<tbody className="divide-y">
							{processed.map((r) => (
								<tr key={r.id} className="">
									{r.rowSpan > 0 && (
										<td className="px-4 py-2 text-sm " rowSpan={r.rowSpan}>
											<div>
												<span>{r.asset?.code || "N/A"}</span>
												<span className="pl-1 opacity-75 text-xs">
													@ {r.asset?.location?.location_name || "N/A"}
												</span>
											</div>
										</td>
									)}
									<td className="px-4 py-2 text-sm ">
										{r.item?.item?.name || "N/A"}
									</td>
									<td className="px-4 py-2 text-sm ">
										{r.item?.criteria || "N/A"}
									</td>
									<td className="px-4 py-2 text-sm ">
										{r.item_status || "N/A"}
									</td>
									<td className="px-4 py-2 text-sm ">{r.remarks || "N/A"}</td>
								</tr>
							))}
						</tbody>
					</table>
					<div className="border border-base-content/10 w-full p-2">
						notes: {selectedInstance.notes || "N/A"}
					</div>
				</div>
			</Modal>
		</div>
	);
}

export default ChecklistInstanceList;
