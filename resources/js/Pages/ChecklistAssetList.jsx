import MultiSelectSearchableDropdown from "@/Components/MultiSelectSearchableDropdown";
import { useFetch } from "@/Hooks/useFetch";
import { useChecklistStore } from "@/Store/checklistStore";
import React, { useCallback, useState, useEffect, useRef } from "react";
import { FaCaretDown, FaPen, FaPlus, FaSearch, FaTrash } from "react-icons/fa";
import TanstackTable from "@/Components/tanStackTable/TanstackTable";
import { useEditableTable } from "@/Hooks/useEditableTable";
import ReadOnlyColumns from "@/Components/tanStackTable/ReadOnlyColumn";
import CheckBoxColumn from "@/Components/tanStackTable/CheckBoxColumn";
import { MdOutlineDelete } from "react-icons/md";
import { GoChecklist } from "react-icons/go";
import Pagination from "@/Components/Pagination";

import ChangeReviewModal from "@/Components/ChangeReviewModal";
import DeleteModal from "@/Components/DeleteModal";
import { useMutation } from "@/Hooks/useMutation";
import getObjectChanges from "@/Utils/getObjectChanges";
import toast from "react-hot-toast";
import { router, usePage } from "@inertiajs/react";
import clsx from "clsx";
import { FaTimes, FaSave } from "react-icons/fa";
import { FaPencil, FaS } from "react-icons/fa6";
import { FaKey } from "react-icons/fa";
import { FaArrowRight } from "react-icons/fa";
import JSONcell from "@/Components/tanStackTable/JSONCell";
import { createClickableCell } from "@/Components/tanStackTable/ClickableCell";
import SearchInput from "./SearchInput";

const assetModalID = "checklist-assets-assets-modal";
const checklistModalID = "checklist-assets-checklist-modal";

const ChecklistAssetList = () => {
    const {
        data: checklists,
        isLoading,
        fetchChecklists,
    } = useChecklistStore();

    const {
        assets: serverAssets,
        search: serverSearch,
        perPage: serverPerPage,
        checklistId: serverChecklistId,
        totalEntries,
    } = usePage().props;

    console.log("🚀 ~ AssetList ~ serverAssets:", serverAssets);
    const start = serverAssets.from;
    const end = serverAssets.to;
    const filteredTotal = serverAssets.total;
    const [maxItem, setMaxItem] = useState(serverPerPage || 30);
    const overallTotal = totalEntries ?? filteredTotal;
    const [selectedChecklist, setSelectedChecklist] = React.useState(null);
    const [searchInput, setSearchInput] = useState(serverSearch || "");
    const [originalData, setOriginalData] = useState({});
    const [selectedEditItem, setSelectedEditItem] = useState([[]]);
    const [selectedCell, setSelectedCell] = useState(null);
    const [currentPage, setCurrentPage] = useState(
        serverAssets.current_page || 1,
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
        data: assets,
        isLoading: isLoadingAsset,
        errorMessage: errorMessageAssets,
        errorData: errorDataAssets,
        cancel: cancelAssets,
        fetch: fetchAssets,
    } = useFetch(route("api.assets.index"), {
        // auto: false,
    });

    const closeModals = () => {
        document.getElementById(assetModalID).close();
        document.getElementById(checklistModalID).close();
    };

    const handleEditModalSelect = (selected) => {
        console.log("🚀 ~ handleEditModalSelect ~ selected:", selected);

        if (selectedCell === null) return;

        console.log(
            "🚀 ~ handleEditModalSelect ~ tedCell?.row?.index,:",
            selectedCell?.row?.index,
        );
        console.log(
            "🚀 ~ handleEditModalSelect ~ selectedCell?.rootKey:",
            selectedCell?.rootKey,
        );
        console.log("🚀 ~ handleEditModalSelect ~ selected[0]:", selected[0]);
        table.options.meta?.updateData(
            selectedCell?.row?.index,
            selectedCell?.rootKey,
            selected[0],
        );

        closeModals();
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
                accessorKey: "asset.code",
                accessorFn: (row) => row.asset?.code,
                header: () => "Asset",
                size: 250,
                cell: createClickableCell({
                    modalID: assetModalID,
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
        useEditableTable(serverAssets.data || [], columns);

    const goToPageCheckItem = (page) => {
        fetchAssets({
            search: checkItemSearchInput,
            page: page,
            perPage: maxItem,
        });
    };

    const handleAssetSearchChange = useCallback((searchValue) => {
        fetchAssets({
            search: searchValue,
            page: 1,
            perPage: maxItem,
        });
        setCheckItemSearchInput(searchValue);
    }, []);

    const refresh = () => {
        router.reload();
    };

    const handleDelete = async () => {
        try {
            await mutate(route("api.checklist-assets.massGenocide"), {
                body: {
                    ids: Object.keys(table.getState().rowSelection),
                },
                method: "DELETE",
            });

            refresh();
            deleteModalRef.current.close();
            toast.success("Checklist assets deleted successfully!");
        } catch (error) {
            toast.error(error?.message);
            console.error(error);
        }
    };

    function handleEditedItemClick(row, value, column) {
        console.log("🚀 ~ handleEditedItemClick ~ row:", row);
        const rootKey = column?.columnDef?.accessorKey?.split(".")[0];
        setSelectedCell({ rootKey, row, value, column });
        setSelectedEditItem([value]);
    }

    const deleteModalRef = useRef(null);

    const [changesToReview, setChangesToReview] = useState([]);
    const saveChangeIDModal = "save_change__checklist_item_modal_id";

    const saveChanges = async () => {
        try {
            await mutate(route("api.checklist-assets.bulkUpdate"), {
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
        const rows = serverAssets.data || [];
        console.log("🚀🚀🚀🚀 setData AssetList ~ rows:", rows);
        setData(rows);

        const map = {};
        rows.forEach((row) => {
            map[row.id] = row;
        });
        setOriginalData(map);
        setEditedRows({});
    }, [serverAssets]);

    const handleAddNewChecklist = () => {
        router.visit(route("checklist-items.create"));
    };

    const handleSaveClick = () => {
        const changes = getObjectChanges(editedRows, originalData);
        if (changes.length === 0) {
            alert("No changes to save.");
            return;
        }
        console.log("🚀 ~ handleSaveClick ~ changes:", changes);
        document.getElementById(saveChangeIDModal).showModal();
        setChangesToReview(changes);
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

    const handleAddNewAsset = () => {
        setData((prevData) => {
            return [
                ...prevData,
                {
                    id: `new-${table.getRowCount() + 1}`,
                    asset_id: null,
                    checklist_id: selectedChecklist?.id,
                    modified_by: null,
                    modified_at: null,
                },
            ];
        });
        setEditedRows((prevData) => {
            return {
                ...prevData,
                [`new-${table.getRowCount() + 1}`]: {
                    id: `new-${table.getRowCount() + 1}`,
                    asset_id: null,
                    checklist_id: selectedChecklist?.id,
                    modified_by: null,
                    modified_at: null,
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

    React.useEffect(() => {
        if (!checklists || checklists?.length === 0) {
            return;
        }

        const selectedChecklist =
            checklists.find((c) => c.id === Number(serverChecklistId)) ||
            checklists[0];

        setSelectedChecklist(selectedChecklist);
    }, [checklists, serverChecklistId]);

    const reload = (checklistId) => {
        router.reload({
            data: {
                search: searchInput,
                perPage: maxItem,
                page: currentPage,
                checklistId: checklistId,
            },
            preserveState: true,
            preserveScroll: true,
        });
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
                        initialSearchInput={searchInput}
                        onSearchChange={setSearchInput}
                    />
                </div>

                <MultiSelectSearchableDropdown
                    modalId={checklistModalID}
                    options={
                        checklists?.map((checklist) => ({
                            value: checklist.name,
                            label: checklist.form_control_no,
                            original: checklist,
                        })) || []
                    }
                    onChange={(value) => {
                        console.log("🚀 ~ ChecklistList ~ value:", value);
                        // setSelectedChecklist(value[0]);
                        reload(value[0]?.id);
                    }}
                    returnKey="original"
                    defaultSelectedOptions={
                        selectedChecklist?.name ? [selectedChecklist.name] : []
                    }
                    controlledSelectedOptions={
                        selectedChecklist?.name ? [selectedChecklist.name] : []
                    }
                    customButtonLabel={({ selectedOptions }) => {
                        return (
                            <div>
                                {selectedOptions.length > 0 ? (
                                    <div className="flex items-center justify-between w-full">
                                        <h1 className="w-full text-lg">
                                            Checklist of {selectedOptions[0]}
                                        </h1>
                                        <FaCaretDown className="inline-block ml-2" />
                                    </div>
                                ) : (
                                    "Select Checklist"
                                )}
                            </div>
                        );
                    }}
                    disableSelectedContainer
                    disableClearSelection
                    disableTooltip
                    isLoading={isLoading}
                    itemName="Checklist List"
                    prompt="Select Checklist"
                    contentClassName="h-50"
                    buttonSelectorClassName="w-full min-h-8 h-auto btn-soft btn-primary text-left"
                    singleSelect
                />

                <TanstackTable table={table} />

                <Pagination
                    links={serverAssets.links}
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
            </div>
        </div>
    );
};

export default ChecklistAssetList;
