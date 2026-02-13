import Modal from "@/Components/Modal";
import Pagination from "@/Components/Pagination";
import { useMutation } from "@/Hooks/useMutation";
import { useToast } from "@/Hooks/useToast";
import { Link, router, usePage } from "@inertiajs/react";
import clsx from "clsx";
import { useEffect, useRef, useState } from "react";
import { FaEdit, FaPlus, FaTrash } from "react-icons/fa";
import SearchInput from "./SearchInput";

const ChemicalSDSList = () => {
	const toast = useToast();

	const {
		chemicalSDS: serverChemicalSDS,
		search: serverSearch,
		perPage: serverPerPage,
		totalEntries,
	} = usePage().props;

	console.group("🚀 ~ Chemicallist ~ serverUtilityTrash");
	console.log("🚀 ~ Chemicallist ~ serverUtilityTrash:", serverChemicalSDS);
	console.log("🚀 ~ Chemicallist ~ serverSearch:", serverSearch);
	console.log("🚀 ~ Chemicallist ~ serverPerPage:", serverPerPage);
	console.log("🚀 ~ Chemicallist ~ totalEntries:", totalEntries);
	console.groupEnd();

	const deleteModalRef = useRef(null);
	const [searchInput, setSearchInput] = useState(serverSearch || "");

	const [maxItem, setMaxItem] = useState(serverPerPage || 10);
	const [selectedEntry, setSelectedEntry] = useState(null);
	const {
		mutate,
		isLoading: isMutateLoading,
		errorMessage: mutateErrorMessage,
		cancel: mutateCancel,
	} = useMutation();

	useEffect(() => {
		const timer = setTimeout(() => {
			router.reload({
				data: {
					search: searchInput,
					perPage: maxItem,
					page: 1,
				},
				preserveState: true,
				preserveScroll: true,
			});
		}, 700);

		return () => clearTimeout(timer);
	}, [searchInput]);

	const goToPage = (page) => {
		router.reload({
			data: {
				search: searchInput,
				perPage: maxItem,
				page,
			},
			preserveState: true,
			preserveScroll: true,
		});
	};

	const changeMaxItemPerPage = (maxItem) => {
		router.reload({
			data: {
				search: searchInput,
				perPage: maxItem,
				page: 1,
			},
			preserveState: true,
			preserveScroll: true,
		});
		setMaxItem(maxItem);
	};

	const refresh = () => {
		router.reload({
			data: {
				search: searchInput,
				perPage: maxItem,
				currentPage,
			},
			preserveState: true,
			preserveScroll: true,
		});
	};

	const handleDelete = async () => {
		try {
			await mutate(
				route("api.chemicals.delete", {
					id: selectedEntry.id,
				}),
				{
					method: "DELETE",
					body: {
						id: selectedEntry.id,
					},
				},
			);

			refresh();

			deleteModalRef.current.close();
			toast.success("Entry verified successfully!");
		} catch (error) {
			toast.error(mutateErrorMessage);
			console.error(error);
		}
	};

	const status = (status) => {
		return (
			<div
				className={clsx("border text-center", {
					"bg-green-300/75 font-bold border-green-300 text-neutral":
						status === "updated",
					"bg-red-400/10 border-red-400 text-red-400": status === "obsolete",
				})}
			>
				{status}
			</div>
		);
	};

	return (
		<>
			<div className="w-full px-4">
				<div className="flex items-center justify-between text-center">
					<h1 className="text-base font-bold">Chemical List</h1>
					<Link href={route("chemicals.create")} className="btn btn-primary">
						<FaPlus /> Add New
					</Link>
				</div>

				<div className="flex items-center justify-between py-4">
					<div>
						<div className="dropdown dropdown-bottom">
							<div tabIndex={0} className="m-1 btn">
								{`Show ${maxItem} items`}
							</div>
							<ul
								tabIndex={0}
								className="p-2 shadow-lg dropdown-content menu bg-base-100 rounded-lg z-1 w-52"
							>
								{[10, 25, 50, 100].map((item) => (
									<li key={item}>
										<a
											onClick={() => {
												changeMaxItemPerPage(item);
											}}
											className="flex items-center justify-between"
										>
											{item}
											{maxItem === item && (
												<span className="font-bold text-green-500">✔</span>
											)}
										</a>
									</li>
								))}
							</ul>
						</div>
					</div>

					<SearchInput
						placeholder="search by emp id or reference no."
						initialSearchInput={searchInput}
						onSearchChange={setSearchInput}
					/>
				</div>

				<table className="table w-full table-auto table-xs">
					<thead>
						<tr>
							<th>ID</th>
							<th>Chemical Name</th>
							<th>Check Date</th>
							<th>Status</th>
							<th>Remarks</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						{serverChemicalSDS.data.map((entry) => (
							<tr key={entry.id}>
								<td>{entry.id}</td>
								<td>{entry.chemical.name || "-"}</td>
								<td>{entry.check_date || "-"}</td>
								<td>{status(entry.status)}</td>
								<td>{entry.remarks || "-"}</td>
								<td className="flex flex-col lg:flex-row">
									<Link
										href={route("chemicals.edit", {
											id: entry.id,
										})}
										className="btn btn-ghost btn-sm btn-primary"
									>
										<FaEdit />
									</Link>
									<a
										href="#"
										className="btn btn-ghost btn-sm text-error"
										onClick={() => {
											setSelectedEntry(entry);
											deleteModalRef.current.open();
										}}
									>
										<FaTrash />
									</a>
								</td>
							</tr>
						))}
					</tbody>
				</table>

				<Pagination
					links={serverChemicalSDS?.links}
					currentPage={serverChemicalSDS?.current_page}
					goToPage={goToPage}
					filteredTotal={serverChemicalSDS?.total}
					overallTotal={totalEntries}
					start={serverChemicalSDS?.from}
					end={serverChemicalSDS?.to}
				/>
			</div>
			<Modal
				ref={deleteModalRef}
				id="deleteChemicalsEntryModal"
				title={`Delete ${selectedEntry?.name}`}
				onClose={() => deleteModalRef.current?.close()}
				className="max-w-lg"
			>
				<p className="px-2 pt-4">
					This action cannot be undone. Delete this entry?
				</p>

				<p
					className="p-2 border rounded-lg bg-error/10 text-error"
					style={{
						visibility: mutateErrorMessage ? "visible" : "hidden",
					}}
				>
					{mutateErrorMessage || "placeholder"}
				</p>

				<div className="flex justify-end gap-2 pt-4">
					<button
						type="button"
						className="btn btn-error"
						onClick={async () => {
							await handleDelete();
						}}
						disabled={isMutateLoading}
					>
						{isMutateLoading ? (
							<>
								<span className="loading loading-spinner"></span> Delete
							</>
						) : (
							"Confirm Delete"
						)}
					</button>

					<button
						className="btn btn-outline"
						onClick={() => deleteModalRef.current?.close()}
					>
						Cancel
					</button>
				</div>
			</Modal>
		</>
	);
};

export default ChemicalSDSList;
