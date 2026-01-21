import { useMutation } from "@/Hooks/useMutation";
import {
    DATE_ONLY_FORMAT,
    formatTimestamp,
} from "@/Utils/formatISOTimestampToDate";
import { usePage, router, Link } from "@inertiajs/react";
import { useEffect, useState, useRef } from "react";
import Modal from "@/Components/Modal";
import { useToast } from "@/Hooks/useToast";
import Pagination from "@/Components/Pagination";
import { FaEdit, FaPlus, FaTrash } from "react-icons/fa";
import SearchInput from "./SearchInput";

const HazardousWasteLogSheet = () => {
    const toast = useToast();

    const {
        hazardousWaste: serverHazardousWaste,
        search: serverSearch,
        perPage: serverPerPage,
        totalEntries,
    } = usePage().props;

    console.group("🚀 ~ UtilityTrashList ~ serverUtilityTrash");
    console.log(
        "🚀 ~ UtilityTrashList ~ serverUtilityTrash:",
        serverHazardousWaste,
    );
    console.log("🚀 ~ UtilityTrashList ~ serverSearch:", serverSearch);
    console.log("🚀 ~ UtilityTrashList ~ serverPerPage:", serverPerPage);
    console.log("🚀 ~ UtilityTrashList ~ totalEntries:", totalEntries);
    console.groupEnd();

    const start = serverHazardousWaste.from;
    const end = serverHazardousWaste.to;
    const filteredTotal = serverHazardousWaste.total;
    const overallTotal = totalEntries ?? filteredTotal;
    const deleteModalRef = useRef(null);
    const [searchInput, setSearchInput] = useState(serverSearch || "");

    const [maxItem, setMaxItem] = useState(serverPerPage || 10);
    const [selectedEntry, setSelectedEntry] = useState(null);
    const [currentPage, setCurrentPage] = useState(
        serverHazardousWaste.current_page || 1,
    );

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
            setCurrentPage(1);
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
        setCurrentPage(page);
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
                route("api.hazardous-log-sheet.delete", {
                    reference_no: selectedEntry.reference_no,
                }),
                {
                    method: "DELETE",
                    body: {
                        reference_no: selectedEntry.reference_no,
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

    return (
        <>
            <div className="w-full px-4">
                <div className="flex items-center justify-between text-center">
                    <h1 className="text-base font-bold">
                        Hazardous Waste Material Turn-Over Logsheet
                    </h1>
                    <Link
                        href={route("hazardous-log-sheet.create")}
                        className="btn btn-primary"
                    >
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
                                                <span className="font-bold text-green-500">
                                                    ✔
                                                </span>
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
                            <th>Reference No.</th>
                            <th>Requestor</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {serverHazardousWaste.data.map((entry) => (
                            <tr key={entry.reference_no}>
                                <td>{entry.reference_no}</td>
                                <td>
                                    {entry?.requestor?.EMPNAME || "-"}

                                    <span className="pl-2 opacity-75">
                                        ({entry?.requestor?.EMPLOYID})
                                    </span>
                                </td>
                                <td>{formatTimestamp(entry?.date)}</td>
                                <td className="flex flex-col lg:flex-row">
                                    <Link
                                        href={route(
                                            "hazardous-log-sheet.edit",
                                            {
                                                reference_no:
                                                    entry.reference_no,
                                            },
                                        )}
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
                    links={serverHazardousWaste.links}
                    currentPage={currentPage}
                    goToPage={goToPage}
                    filteredTotal={filteredTotal}
                    overallTotal={overallTotal}
                    start={start}
                    end={end}
                />
            </div>
            <Modal
                ref={deleteModalRef}
                id="deleteHazardousWasteEntryModal"
                title={`Delete ${
                    selectedEntry?.performed_by?.EMPNAME
                } on ${formatTimestamp(selectedEntry?.date, DATE_ONLY_FORMAT)}`}
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
                        className="btn btn-error"
                        onClick={async () => {
                            await handleDelete();
                        }}
                        disabled={isMutateLoading}
                    >
                        {isMutateLoading ? (
                            <>
                                <span className="loading loading-spinner"></span>{" "}
                                Delete
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

export default HazardousWasteLogSheet;
