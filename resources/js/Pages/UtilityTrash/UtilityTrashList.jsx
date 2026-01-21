import { useMutation } from "@/Hooks/useMutation";
import {
    DATE_ONLY_FORMAT,
    formatTimestamp,
    TIME_ONLY_FORMAT,
} from "@/Utils/formatISOTimestampToDate";
import { usePage, router, Link } from "@inertiajs/react";
import { useEffect, useState, useRef } from "react";
import { FaEdit, FaExclamationTriangle, FaTrash } from "react-icons/fa";
import { FaPlus } from "react-icons/fa6";
import Modal from "@/Components/Modal";
import { useToast } from "@/Hooks/useToast";
import Pagination from "@/Components/Pagination";
import UpdateChecklist from "./UpdateChecklist";
import TimeLine from "@/Components/TimeLine";
import { TOGGLE_UTILITY_TRASH_STATUS_BUTTONS } from "@/Constants/togglerButtons";
import TogglerButtons from "@/Components/TogglerButtons";
import clsx from "clsx";
import SearchInput from "../SearchInput";
const UtilityTrashList = () => {
    const toast = useToast();

    const {
        utilityTrash: serverUtilityTrash,
        isNotVerified: serverIsNotVerified,
        isVerified: serverIsVerified,
        search: serverSearch,
        perPage: serverPerPage,
        totalEntries,
    } = usePage().props;

    console.group("🚀 ~ UtilityTrashList ~ serverUtilityTrash");
    console.log(
        "🚀 ~ UtilityTrashList ~ serverUtilityTrash:",
        serverUtilityTrash,
    );
    console.log("🚀 ~ UtilityTrashList ~ serverSearch:", serverSearch);
    console.log("🚀 ~ UtilityTrashList ~ serverPerPage:", serverPerPage);
    console.log("🚀 ~ UtilityTrashList ~ totalEntries:", totalEntries);
    console.groupEnd();

    const start = serverUtilityTrash.from;
    const end = serverUtilityTrash.to;
    const filteredTotal = serverUtilityTrash.total;
    const overallTotal = totalEntries ?? filteredTotal;
    const verifyModalRef = useRef(null);
    const performChecklistModalRef = useRef(null);
    const [searchInput, setSearchInput] = useState(serverSearch || "");
    const [performDate, setPerformDate] = useState(null);
    const [statusFilters, setStatusFilters] = useState({
        not_verified: serverIsNotVerified,
        verified: serverIsVerified,
    });

    const handleToggleStatus = (name, key) => {
        setStatusFilters((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    const toggleAllStatus = () => {
        setStatusFilters((prev) => ({
            ...prev,
            not_verified: true,
            verified: true,
        }));
    };

    const [maxItem, setMaxItem] = useState(serverPerPage || 10);
    const [selectedEntry, setSelectedEntry] = useState(null);
    const [currentPage, setCurrentPage] = useState(
        serverUtilityTrash.current_page || 1,
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
                    isNotVerified: statusFilters.not_verified,
                    isVerified: statusFilters.verified,
                    perPage: maxItem,
                    page: 1,
                },
                preserveState: true,
                preserveScroll: true,
            });
            setCurrentPage(1);
        }, 700);

        return () => clearTimeout(timer);
    }, [searchInput, statusFilters]);

    const goToPage = (page) => {
        router.reload({
            data: {
                search: searchInput,
                isNotVerified: statusFilters.not_verified,
                isVerified: statusFilters.verified,
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
                isNotVerified: statusFilters.not_verified,
                isVerified: statusFilters.verified,
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
                isNotVerified: statusFilters.not_verified,
                isVerified: statusFilters.verified,
                perPage: maxItem,
                currentPage,
            },
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePerformChecklist = async () => {
        try {
            await mutate(route("api.utility-trash.perform"), {
                method: "POST",
                body: {
                    date: performDate,
                },
            });

            refresh();
            performChecklistModalRef.current.close();
            toast.success("Checklist performed successfully!");
        } catch (error) {
            toast.error(mutateErrorMessage);
            console.error(error);
        }
    };

    const handleVerify = async () => {
        try {
            await mutate(
                route("api.utility-trash.verify", {
                    id: selectedEntry.id,
                }),
                {
                    method: "PATCH",
                    body: {
                        id: selectedEntry.id,
                    },
                },
            );

            refresh();

            verifyModalRef.current.close();
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
                    <h1 className="text-base font-bold">Utility Trash</h1>
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

                        <TogglerButtons
                            id="toggle-performed-verified-all"
                            toggleButtons={TOGGLE_UTILITY_TRASH_STATUS_BUTTONS}
                            visibleBars={statusFilters}
                            toggleBar={handleToggleStatus}
                            toggleAll={toggleAllStatus}
                        />
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
                        <SearchInput
                            placeholder="Search"
                            initialSearchInput={searchInput}
                            onSearchChange={setSearchInput}
                        />
                    </label>
                </div>

                <div className="w-full gap-2 justify-center h-20 flex items-center border border-primary bg-primary/10">
                    <button
                        className="btn btn-primary"
                        onClick={() => {
                            performChecklistModalRef.current.open();
                            setPerformDate(new Date());
                        }}
                    >
                        <span>Update checklist now</span>
                    </button>
                    <div className="w-40">
                        <UpdateChecklist role={"user"} />
                    </div>
                </div>

                <div className="w-full flex justify-center bg-base-300 px-2 border border-base-content-dim">
                    <TimeLine
                        ranges={[
                            {
                                startHour: 9,
                                endHour: 9.5,
                                color: "var(--color-primary)",
                            },
                            {
                                startHour: 12.5,
                                endHour: 13,
                                color: "var(--color-primary)",
                            },
                            {
                                startHour: 17,
                                endHour: 17.5,
                                color: "var(--color-primary)",
                            },
                            {
                                startHour: 21,
                                endHour: 21.5,
                                color: "var(--color-primary)",
                            },
                            {
                                startHour: 0.5,
                                endHour: 1,
                                color: "var(--color-primary)",
                            },
                            {
                                startHour: 5,
                                endHour: 5.5,
                                color: "var(--color-primary)",
                            },
                        ]}
                    />
                </div>

                <table className="table w-full table-auto table-xs">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Performed By</th>
                            <th>Verified By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {serverUtilityTrash.data.map((entry) => (
                            <tr key={entry.id}>
                                <td>{entry.id}</td>
                                <td>
                                    {formatTimestamp(
                                        entry?.date,
                                        DATE_ONLY_FORMAT,
                                    )}
                                </td>
                                <td>
                                    {formatTimestamp(
                                        entry?.date,
                                        TIME_ONLY_FORMAT,
                                    )}
                                </td>
                                <td>
                                    {entry?.performed_by?.EMPNAME || "-"}

                                    <span className="pl-2 opacity-75">
                                        ({entry?.performed_by?.EMPLOYID})
                                    </span>
                                </td>
                                <td>{entry?.verified_by?.EMPNAME || "-"}</td>
                                {/* <Link
                                        href={route("partname.edit", {
                                            id: part.ppc_partnamedb_id,
                                            search: searchInput,
                                            perPage: maxItem,
                                            page: currentPage,
                                        })}
                                        className="btn btn-ghost btn-sm btn-primary"
                                    >
                                        <FaEdit />
                                    </Link>
                                    <a
                                        href="#"
                                        className="btn btn-ghost btn-sm text-error"
                                        onClick={() => {
                                            setSelectedPart(part);
                                            deleteModalRef.current.open();
                                        }}
                                    >
                                        <FaTrash />
                                    </a> */}
                                <td className="flex flex-col lg:flex-row">
                                    {entry?.verified_by === null ? (
                                        <a
                                            href="#"
                                            className={clsx(
                                                "btn btn-secondary btn-sm",
                                                entry?.verified_by
                                                    ? "hidden"
                                                    : "",
                                            )}
                                            onClick={() => {
                                                setSelectedEntry(entry);
                                                console.log(
                                                    "🚀 ~ UtilityTrashList ~ entry:",
                                                    entry,
                                                );

                                                verifyModalRef.current.open();
                                            }}
                                        >
                                            Verify
                                        </a>
                                    ) : (
                                        <span className="italic opacity-50">
                                            - Verified -
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <Pagination
                    links={serverUtilityTrash.links}
                    currentPage={currentPage}
                    goToPage={goToPage}
                    filteredTotal={filteredTotal}
                    overallTotal={overallTotal}
                    start={start}
                    end={end}
                />
            </div>
            <Modal
                ref={verifyModalRef}
                id="verifyUtilityTrashEntryModal"
                title={`Verify ${
                    selectedEntry?.performed_by?.EMPNAME
                } on ${formatTimestamp(selectedEntry?.date, DATE_ONLY_FORMAT)}`}
                onClose={() => verifyModalRef.current?.close()}
                className="max-w-lg"
            >
                <p className="px-2 pt-4">
                    This action cannot be undone. Verify this entry?
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
                            await handleVerify();
                        }}
                        disabled={isMutateLoading}
                    >
                        {isMutateLoading ? (
                            <>
                                <span className="loading loading-spinner"></span>{" "}
                                Verifying
                            </>
                        ) : (
                            "Confirm Verify"
                        )}
                    </button>

                    <button
                        className="btn btn-outline"
                        onClick={() => verifyModalRef.current?.close()}
                    >
                        Cancel
                    </button>
                </div>
            </Modal>

            <Modal
                ref={performChecklistModalRef}
                id="performUtilityTrashEntryModal"
                title={`Perform at ${formatTimestamp(performDate)}`}
                onClose={() => performChecklistModalRef.current?.close()}
                className="max-w-lg"
            >
                <p className="px-2 pt-4">
                    Make sure this is the correct entry before proceeding.
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
                            await handlePerformChecklist();
                        }}
                        disabled={isMutateLoading}
                    >
                        {isMutateLoading ? (
                            <>
                                <span className="loading loading-spinner"></span>{" "}
                                Adding entry
                            </>
                        ) : (
                            "Confirm Checklist"
                        )}
                    </button>

                    <button
                        className="btn btn-outline"
                        onClick={() =>
                            performChecklistModalRef.current?.close()
                        }
                    >
                        Cancel
                    </button>
                </div>
            </Modal>
        </>
    );
};

export default UtilityTrashList;
