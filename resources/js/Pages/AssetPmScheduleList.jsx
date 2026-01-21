import MultiSelectSearchableDropdown from "@/Components/MultiSelectSearchableDropdown";
import { useFetch } from "@/Hooks/useFetch";
import React, { useCallback, useState, useEffect, useRef } from "react";
import { FaPlus, FaSearch } from "react-icons/fa";
import TanstackTable from "@/Components/tanStackTable/TanstackTable";
import { useEditableTable } from "@/Hooks/useEditableTable";
import ReadOnlyColumns from "@/Components/tanStackTable/ReadOnlyColumn";
import CheckBoxColumn from "@/Components/tanStackTable/CheckBoxColumn";
import { MdOutlineDelete } from "react-icons/md";
import Pagination from "@/Components/Pagination";

import ChangeReviewModal from "@/Components/ChangeReviewModal";
import DeleteModal from "@/Components/DeleteModal";
import { useMutation } from "@/Hooks/useMutation";
import getObjectChanges from "@/Utils/getObjectChanges";
import toast from "react-hot-toast";
import { router, usePage } from "@inertiajs/react";
import clsx from "clsx";
import { FaTimes, FaSave } from "react-icons/fa";
import { FaPencil } from "react-icons/fa6";
import JSONcell from "@/Components/tanStackTable/JSONCell";
import { createClickableCell } from "@/Components/tanStackTable/ClickableCell";
import SearchInput from "./SearchInput";
const checkItemListModalID = "checklist-item-modal";

const scheduleModalID = "schedule-asset-pm-schedule-modal";
const assetModalID = "asset-pm-schedule-assets-modal";

const AssetPmScheduleList = () => {
    const {
        assetPmSchedules: serverAssetPmSchedules,
        search: serverSearch,
        perPage: serverPerPage,
        totalEntries,
    } = usePage().props;

    console.log(
        "🚀 ~ AssetList ~ serverAssetPmSchedules:",
        serverAssetPmSchedules,
    );

    const start = serverAssetPmSchedules.from;
    const end = serverAssetPmSchedules.to;
    const filteredTotal = serverAssetPmSchedules.total;
    const [selectedChecklist, setSelectedChecklist] = React.useState(null);
    const [maxItem, setMaxItem] = useState(serverPerPage || 30);
    const overallTotal = totalEntries ?? filteredTotal;
    const [searchInput, setSearchInput] = useState(serverSearch || "");
    const [originalData, setOriginalData] = useState({});
    const [selectedEditItem, setSelectedEditItem] = useState([[]]);
    const [selectedCell, setSelectedCell] = useState(null);
    const [currentPage, setCurrentPage] = useState(
        serverAssetPmSchedules.current_page || 1,
    );
    const [checkItemSearchInput, setCheckItemSearchInput] = useState("");

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

    const {
        mutate: mutate,
        isLoading: isMutateLoading,
        errorMessage: mutateErrorMessage,
        errorData: mutateErrorData,
        cancel: mutateCancel,
    } = useMutation();

    const {
        data: schedules,
        isLoading: isLoadingSchedules,
        errorMessage: errorMessageSchedules,
        errorData: errorDataSchedules,
        cancel: cancelSchedules,
        fetch: fetchSchedules,
    } = useFetch(route("api.schedules.index"), {
        // auto: false,
    });

    const {
        data: assets,
        isLoading: isLoadingAsset,
        errorMessage: errorMessageAssets,
        errorData: errorDataAssets,
        cancel: cancelAssets,
        fetch: fetchAssets,
    } = useFetch(route("api.assets.index"), {
        // auto: false,
    });

    console.log("🚀 ~ AssetList ~ schedules:", schedules);

    const handleEditModalSelect = (selected) => {
        if (selectedCell === null) return;
        table.options.meta?.updateData(
            selectedCell?.row?.index,
            selectedCell?.rootKey,
            selected[0],
        );

        document.getElementById(scheduleModalID).close();
    };

    const columns = React.useMemo(
        () => [
            CheckBoxColumn,
            ReadOnlyColumns({
                accessorKey: "id",
                header: "ID",
                options: { size: 60, enableHiding: false },
            }),
            {
                header: "Asset Info",
                columns: [
                    {
                        accessorKey: "assets.code",
                        header: "Code",
                        cell: createClickableCell({
                            modalID: assetModalID,
                            handleCellClick: handleEditedItemClick,
                        }),
                    },
                    ReadOnlyColumns({
                        accessorKey: "assets.location.location_name",
                        header: "Location",
                        options: { size: 160 },
                    }),
                ],
            },
            // {
            //     accessorKey: "assets.code",
            //     header: () => "Code",
            //     size: 250,
            // },
            // {
            //     accessorKey: "assets.location",
            //     header: () => "Code",
            //     size: 250,
            // },
            {
                accessorKey: "schedule.schedule_name",
                header: () => "Schedule",
                accessorFn: (row) => row.schedule?.schedule_name,
                size: 500,
                // cell: JSONcell,
                cell: createClickableCell({
                    modalID: scheduleModalID,
                    deletable: false,
                    handleCellClick: handleEditedItemClick,
                }),
            },
            {
                header: "Audit Info",
                columns: [
                    ReadOnlyColumns({
                        accessorKey: "modified_by",
                        header: "Modified By",
                    }),
                    ReadOnlyColumns({
                        accessorKey: "modified_at",
                        header: "Modified At",
                        options: { size: 160 },
                    }),
                ],
            },
        ],
        [],
    );

    const { table, data, setData, editedRows, setEditedRows } =
        useEditableTable(serverAssetPmSchedules.data || [], columns);

    const goToPageCheckItem = (page) => {
        fetchAssets({
            search: checkItemSearchInput,
            page: page,
            perPage: maxItem,
        });
    };

    const refresh = () => {
        router.reload();
    };

    const handleDelete = async () => {
        try {
            await mutate(route("api.asset-pm-schedules.massGenocide"), {
                body: {
                    ids: Object.keys(table.getState().rowSelection),
                },
                method: "DELETE",
            });

            refresh();
            deleteModalRef.current.close();
            toast.success("Locations deleted successfully!");
        } catch (error) {
            toast.error(error?.message);
            console.error(error);
        }
    };

    function handleEditedItemClick(row, value, column) {
        const rootKey = column?.columnDef?.accessorKey?.split(".")[0];
        setSelectedCell({ rootKey, row, value, column });
        setSelectedEditItem([value]);
    }

    console.log("🚀 ~ ChecklistList ~ selectedChecklist:", selectedChecklist);
    const deleteModalRef = useRef(null);

    const [changesToReview, setChangesToReview] = useState([]);
    const saveChangeIDModal = "save_change__checklist_item_modal_id";

    const saveChanges = async () => {
        try {
            await mutate(route("api.asset-pm-schedules.bulkUpdate"), {
                method: "PATCH",
                body: editedRows,
            });
            document.getElementById(saveChangeIDModal).close();

            toast.success("Changes updated successfully!");
            console.log("zzzzzzzz");

            refresh();
        } catch (error) {
            toast.error(error.message);
            console.error(error);
        }
    };

    const handleAssetSearchChange = useCallback((searchValue) => {
        fetchAssets({
            search: searchValue,
            page: 1,
            perPage: maxItem,
        });
        setCheckItemSearchInput(searchValue);
    }, []);

    const handleResetChanges = () => {
        if (Object.keys(editedRows).length === 0) {
            alert("No changes to reset.");
            return;
        }

        if (!confirm("Are you sure you want to discard all changes?")) return;

        setEditedRows({});
        setChangesToReview([]);
        const originalRows = Object.values(originalData);
        console.log("🚀 ~ handleResetChanges ~ originalRows:", originalRows);
        setData(originalRows);
    };

    React.useEffect(() => {
        console.log("editedRows", editedRows);
    }, [editedRows]);

    React.useEffect(() => {
        const rows = serverAssetPmSchedules.data || [];
        setData(rows);

        const map = {};
        rows.forEach((row) => {
            map[row.id] = row;
        });
        setOriginalData(map);
        setEditedRows({});
    }, [serverAssetPmSchedules]);

    const handleAddNewChecklist = () => {
        router.visit(route("checklist-items.create"));
    };

    const handleSaveClick = () => {
        const changes = getObjectChanges(editedRows, originalData);
        if (changes.length === 0) {
            alert("No changes to save.");
            return;
        }
        document.getElementById(saveChangeIDModal).showModal();
        setChangesToReview(changes);
    };

    const handleAddNewAsset = () => {
        setData((prevData) => {
            return [
                ...prevData,
                {
                    id: `new-${table.getRowCount() + 1}`,
                    code: null,
                    properties: null,
                    location: null,
                    created_at: null,
                    updated_at: null,
                },
            ];
        });
        setEditedRows((prevData) => {
            return {
                ...prevData,
                [`new-${table.getRowCount() + 1}`]: {
                    id: `new-${table.getRowCount() + 1}`,
                    code: null,
                    properties: null,
                    location: null,
                    created_at: null,
                    updated_at: null,
                },
            };
        });
    };

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

    const commonEditModalConfig = {
        defaultSelectedOptions: [selectedEditItem],
        controlledSelectedOptions: selectedEditItem,
        returnKey: "original",
        singleSelect: true,
        disableTooltip: true,
        disableClearSelection: true,
        useModal: true,
        disableSelectedContainer: true,
        paginated: true,
    };

    return (
        <div>
            <div className="w-full">
                <div className="flex gap-2 sticky right-0 mb-4">
                    <button
                        className="btn btn-primary"
                        onClick={handleAddNewAsset}
                    >
                        <FaPlus className="mr-2" />
                        Add New Asset
                    </button>
                    <button
                        className="btn btn-primary"
                        onClick={handleSaveClick}
                        disabled={Object.keys(editedRows).length === 0}
                    >
                        <FaSave className="mr-2" />
                        Save Changes
                    </button>
                    <button
                        className="btn btn-secondary"
                        onClick={handleResetChanges}
                    >
                        Reset
                    </button>
                    <button
                        className="btn btn-error btn-ghost btn-square"
                        disabled={
                            Object.keys(table.getState().rowSelection)
                                .length === 0
                        }
                        onClick={() => deleteModalRef.current.open()}
                    >
                        <MdOutlineDelete className="w-full h-full" />
                    </button>

                    <SearchInput
                        placeholder="search by asset or checklist"
                        initialSearchInput={searchInput}
                        onSearchChange={setSearchInput}
                    />
                </div>

                <TanstackTable table={table} />

                <Pagination
                    links={serverAssetPmSchedules.links}
                    currentPage={currentPage}
                    goToPage={goToPage}
                    filteredTotal={filteredTotal}
                    overallTotal={overallTotal}
                    start={start}
                    end={end}
                    contentClassName=""
                />

                <ChangeReviewModal
                    modalID={saveChangeIDModal}
                    changes={changesToReview}
                    onClose={() =>
                        document.getElementById(saveChangeIDModal).close()
                    }
                    onSave={saveChanges}
                    isLoading={isMutateLoading}
                />
                <DeleteModal
                    ref={deleteModalRef}
                    id="locationDeleteModal"
                    message="Are you sure you want to delete these locations?"
                    errorMessage={mutateErrorMessage}
                    isLoading={isMutateLoading}
                    onDelete={handleDelete}
                    onClose={() => deleteModalRef.current?.close()}
                />
                <MultiSelectSearchableDropdown
                    modalId={assetModalID}
                    options={
                        assets?.assets?.data?.map((item) => ({
                            value: String(item.code),
                            label: item?.location?.location_name,
                            original: item,
                        })) || []
                    }
                    onChange={handleEditModalSelect}
                    onSearchChange={handleAssetSearchChange}
                    links={assets?.assets?.links || null}
                    currentPage={assets?.assets?.current_page || 1}
                    goToPage={goToPageCheckItem}
                    itemName="Asset List"
                    isLoading={isLoadingAsset}
                    prompt="Select Asset"
                    contentClassName={"h-100"}
                    paginated={true}
                    {...commonEditModalConfig}
                />
                <MultiSelectSearchableDropdown
                    modalId={scheduleModalID}
                    options={
                        schedules?.schedules?.map((item) => ({
                            value: String(item.schedule_name),
                            label: null,
                            original: item,
                        })) || []
                    }
                    onChange={handleEditModalSelect}
                    itemName="Schedule List"
                    isLoading={isLoadingSchedules}
                    prompt="Select Schedule"
                    contentClassName={"h-150"}
                    defaultSelectedOptions={[selectedEditItem]}
                    controlledSelectedOptions={selectedEditItem}
                    {...commonEditModalConfig}
                />
            </div>
        </div>
    );
};

export default AssetPmScheduleList;
