import BulkErrors from "@/Components/BulkErrors";
import ChangeReviewModal from "@/Components/ChangeReviewModal";
import DeleteModal from "@/Components/DeleteModal";
import Pagination from "@/Components/Pagination";
import DropdownCell from "@/Components/tanStackTable/DropDownCell";
import ReadOnlyColumns from "@/Components/tanStackTable/ReadOnlyColumn";
import TanstackTable from "@/Components/tanStackTable/TanstackTable";
import { useEditableTable } from "@/Hooks/useEditableTable";
import { useMutation } from "@/Hooks/useMutation";
import { router, usePage } from "@inertiajs/react";
import React, { useRef, useState } from "react";
import toast from "react-hot-toast";
import { FaPlus, FaSave } from "react-icons/fa";

function ChecklistApprovalConfigList() {
	const deleteModalRef = useRef(null);

	const {
		configs: serverConfigs,
		perPage: serverPerPage,
		totalEntries,
	} = usePage().props;

	const [maxItem] = useState(serverPerPage || 999);

	const {
		mutate,
		isLoading: isMutateLoading,
		errorMessage: mutateErrorMessage,
		errorData: mutateErrorData,
		cancel: mutateCancel,
	} = useMutation();

	const refresh = () => router.reload();

	const columns = React.useMemo(
		() => [
			ReadOnlyColumns({
				accessorKey: "id",
				header: "ID",
				options: { size: 60, enableHiding: false },
			}),
			{
				accessorKey: "user_id",
				header: "Employee ID",
				size: 120,
			},
			ReadOnlyColumns({
				accessorKey: "employee",
				header: "Name",
				options: { size: 200 },
				cell: ({ row }) => {
					const emp = row.original.employee;
					return emp ? (
						<span className="opacity-60 cursor-not-allowed">
							{emp.FIRSTNAME} {emp.LASTNAME}
						</span>
					) : (
						<span className="opacity-50 text-xs cursor-not-allowed">N/A</span>
					);
				},
			}),
			{
				accessorKey: "level",
				header: "Level",
				size: 80,
				cell: ({ row, getValue, table, column }) => (
					<DropdownCell
						row={row}
						getValue={getValue}
						table={table}
						column={column}
						options={[
							{ label: "Level 1", value: 1 },
							{ label: "Level 2", value: 2 },
							{ label: "Level 3", value: 3 },
						]}
					/>
				),
			},
			{
				accessorKey: "instance_type",
				header: "Instance Type",
				size: 140,
				cell: ({ row }) => {
					const type = row.original.instance_type;
					return type ? (
						<span className="badge badge-xs badge-outline">{type}</span>
					) : (
						<span className="opacity-50 text-xs">all types</span>
					);
				},
			},
		],
		[],
	);

	const {
		table,
		editedRows,
		handleAddNewRow,
		handleResetChanges,
		getChanges,
		changes,
	} = useEditableTable(serverConfigs.data || [], columns, {
		isMultipleSelection: false,
	});

	const saveChangeIDModal = "save_approver_config_modal";

	const handleSaveClick = () => {
		const computedChanges = getChanges();
		if (computedChanges.length === 0) {
			alert("No changes to save.");
			return;
		}
		document.getElementById(saveChangeIDModal).showModal();
	};

	const saveChanges = async () => {
		try {
			await mutate(route("api.checklist-approval-config.bulkUpdate"), {
				method: "PATCH",
				body: editedRows,
			});
			document.getElementById(saveChangeIDModal).close();
			toast.success("Approvers updated successfully!");
			refresh();
		} catch (error) {
			toast.error(error.message);
			console.error(error);
		}
	};

	const handleDelete = async () => {
		try {
			await mutate(route("api.checklist-approval-config.massGenocide"), {
				method: "DELETE",
				body: { ids: Object.keys(table.getState().rowSelection) },
			});
			refresh();
			deleteModalRef.current.close();
			toast.success("Approvers deleted successfully!");
		} catch (error) {
			toast.error(error?.message);
			console.error(error);
		}
	};

	const goToPage = (page) => {
		router.reload({
			data: { perPage: maxItem, page },
			preserveState: true,
			preserveScroll: true,
		});
	};

	return (
		<div>
			<h1 className="text-lg font-semibold">Checklist Approval Config</h1>
			<p className="text-xs opacity-50 mb-2">
				Approvers assigned here will be used for late checklist submissions.
				Leave instance type blank to apply to all types.
			</p>

			<div className="flex gap-2 sticky right-0">
				<button
					type="button"
					className="btn btn-primary"
					onClick={() => handleAddNewRow()}
				>
					<FaPlus className="mr-2" />
					Add Approver
				</button>
				<button
					type="button"
					className="btn btn-primary"
					onClick={handleSaveClick}
					disabled={Object.keys(editedRows).length === 0}
				>
					<FaSave className="mr-2" />
					Save Changes
				</button>
				<button
					type="button"
					className="btn btn-secondary"
					onClick={handleResetChanges}
				>
					Reset
				</button>
			</div>

			<div className="px-2 w-full">
				<BulkErrors errors={mutateErrorData?.data || []} />
			</div>

			<Pagination
				links={serverConfigs?.links}
				currentPage={serverConfigs?.current_page}
				goToPage={goToPage}
				filteredTotal={serverConfigs?.total}
				overallTotal={totalEntries}
				start={serverConfigs?.from}
				end={serverConfigs?.to}
			/>

			<TanstackTable table={table} />

			<ChangeReviewModal
				modalID={saveChangeIDModal}
				changes={changes}
				onClose={() => document.getElementById(saveChangeIDModal).close()}
				onSave={saveChanges}
				isLoading={isMutateLoading}
			/>
			<DeleteModal
				ref={deleteModalRef}
				id="approverConfigDeleteModal"
				message="Are you sure you want to remove these approvers?"
				errorMessage={mutateErrorMessage}
				isLoading={isMutateLoading}
				onDelete={handleDelete}
				onClose={() => deleteModalRef.current?.close()}
			/>
		</div>
	);
}

export default ChecklistApprovalConfigList;
