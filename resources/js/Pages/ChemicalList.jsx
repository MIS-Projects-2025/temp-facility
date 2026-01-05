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

const ChemicalList = () => {
    const toast = useToast();

    const {
        chemicals: serverChemicals,
        search: serverSearch,
        perPage: serverPerPage,
        totalEntries,
    } = usePage().props;

    console.group("🚀 ~ Chemicallist ~ serverUtilityTrash");
    console.log("🚀 ~ Chemicallist ~ serverUtilityTrash:", serverChemicals);
    console.log("🚀 ~ Chemicallist ~ serverSearch:", serverSearch);
    console.log("🚀 ~ Chemicallist ~ serverPerPage:", serverPerPage);
    console.log("🚀 ~ Chemicallist ~ totalEntries:", totalEntries);
    console.groupEnd();

    const start = serverChemicals.from;
    const end = serverChemicals.to;
    const filteredTotal = serverChemicals.total;
    const overallTotal = totalEntries ?? filteredTotal;
    const deleteModalRef = useRef(null);
    const [searchInput, setSearchInput] = useState(serverSearch || "");

    const [maxItem, setMaxItem] = useState(serverPerPage || 10);
    const [selectedEntry, setSelectedEntry] = useState(null);
    const [currentPage, setCurrentPage] = useState(
        serverChemicals.current_page || 1
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
                route("api.chemicals.delete", {
                    id: selectedEntry.id,
                }),
                {
                    method: "DELETE",
                    body: {
                        id: selectedEntry.id,
                    },
                }
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
                    <h1 className="text-base font-bold">Chemical List</h1>
                    <Link
                        href={route("chemicals.create")}
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

                    <label className="input">
                        <svg
                            className="h-[1em] opacity-50"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                        >
                            <g
                                strokeLinejoin="round"
                                strokeLinecap="round"
                                strokeWidth="2.5"
                                fill="none"
                                stroke="currentColor"
                            >
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                            </g>
                        </svg>
                        <input
                            type="text"
                            placeholder="search by emp id or reference no."
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                        />
                    </label>
                </div>

                <table className="table w-full table-auto table-xs">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {serverChemicals.data.map((entry) => (
                            <tr key={entry.id}>
                                <td>{entry.id}</td>
                                <td>{entry.name || "-"}</td>
                                <td>{entry.description || "-"}</td>
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
                    links={serverChemicals.links}
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

export default ChemicalList;
