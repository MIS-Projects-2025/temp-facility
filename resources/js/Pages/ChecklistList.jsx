import MultiSelectSearchableDropdown from "@/Components/MultiSelectSearchableDropdown";
import { useFetch } from "@/Hooks/useFetch";
import { useChecklistStore } from "@/Store/checklistStore";
import React, { useCallback, useState, useEffect, useRef } from "react";
import { FaCaretDown, FaPen, FaPlus } from "react-icons/fa";
import TanstackTable from "@/Components/tanStackTable/TanstackTable";
import { useEditableTable } from "@/Hooks/useEditableTable";
import ReadOnlyColumns from "@/Components/tanStackTable/ReadOnlyColumn";
import CheckBoxColumn from "@/Components/tanStackTable/CheckBoxColumn";
import { MdOutlineDelete } from "react-icons/md";
import { GoChecklist } from "react-icons/go";

import ChangeReviewModal from "@/Components/ChangeReviewModal";
import DeleteModal from "@/Components/DeleteModal";
import { useMutation } from "@/Hooks/useMutation";
import getObjectChanges from "@/Utils/getObjectChanges";
import toast from "react-hot-toast";
import { router } from "@inertiajs/react";
import clsx from "clsx";
import { FaTimes, FaSave } from "react-icons/fa";
import { FaPencil, FaS } from "react-icons/fa6";

const perPageF3RawPackage = 30;
const checkItemListModalID = "checklist-item-modal";
const scheduleModalID = "schedule-modal";

const ChecklistList = () => {
    const {
        data: checklists,
        isLoading,
        fetchChecklists,
    } = useChecklistStore();
    const [selectedChecklist, setSelectedChecklist] = React.useState(null);
    const [originalData, setOriginalData] = useState({});
    const [selectedEditItem, setSelectedEditItem] = useState([[]]);
    const [selectedCell, setSelectedCell] = useState(null);

    const [checkItemSearchInput, setCheckItemSearchInput] = useState("");

    useEffect(() => {
        fetchChecklists();
    }, []);

    const {
        mutate: mutate,
        isLoading: isMutateLoading,
        errorMessage: mutateErrorMessage,
        errorData: mutateErrorData,
        cancel: mutateCancel,
    } = useMutation();

    const {
        data: checkItems,
        isLoading: isLoadingCheckItems,
        errorMessage: errorMessageCheckItems,
        errorData: errorDataCheckItems,
        cancel: cancelCheckItems,
        fetch: fetchCheckItems,
    } = useFetch(route("api.check-items.index"), {
        auto: false,
    });

    const {
        data: checklistItems,
        isLoading: isChecklistItemsLoading,
        errorMessage: ChecklistItemsErrorMessage,
        fetch: checklistItemsFetch,
        abort: checklistItemsAbort,
    } = useFetch(route("api.checklist-items.all-check-items"), {
        params: {
            checklist_id: selectedChecklist?.id || "",
        },
        auto: false,
    });

    const handleEditModalSelect = (selected) => {
        if (selectedCell === null) return;

        console.log(
            "🚀 ~ handleEditModalSelect ~ tedCell?.row?.index,:",
            selectedCell?.row?.index
        );
        console.log(
            "🚀 ~ handleEditModalSelect ~ selectedCell?.rootKey:",
            selectedCell?.rootKey
        );
        console.log("🚀 ~ handleEditModalSelect ~ selected[0]:", selected[0]);
        table.options.meta?.updateData(
            selectedCell?.row?.index,
            selectedCell?.rootKey,
            selected[0]
        );

        // document.getElementById(checkItemListModalID).close();
    };

    const clickableCell = ({ modalID, deletable = false }) => {
        return ({ table, row, getValue, column }) => {
            const value = getValue();

            return (
                <div
                    onClick={() => {
                        handleEditedItemClick(row, value, column);
                        document.getElementById(modalID).showModal();
                    }}
                    className={clsx(
                        "cursor-pointer group hover:bg-primary/50 w-full flex justify-between items-center px-2 relative",
                        {
                            "text-xs text-base-content/50 italic": !value,
                        }
                    )}
                >
                    <span className="text-primary drop-shadow-md/100 opacity-0 group-hover:opacity-100 transition-opacity top-0 right-0 translate-x-10/12 -translate-y-10/12 absolute">
                        <FaPencil className="w-6 h-6" />
                    </span>
                    <span>{value ?? "none"}</span>
                    {value && deletable && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation();

                                const rootKey =
                                    column?.columnDef?.accessorKey?.split(
                                        "."
                                    )[0];

                                table.options.meta?.updateData(
                                    row?.index,
                                    rootKey,
                                    null
                                );
                            }}
                            className="btn w-4 h-4 btn-error btn-sm btn-circle absolute cursor-pointer right-1 opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            <FaTimes size={12} />
                        </button>
                    )}
                </div>
            );
        };
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
                accessorKey: "checklist_id",
                header: "Checklist ID",
                meta: { hidden: true },
                // cell: (info) => null,
            },
            {
                accessorKey: "item.name",
                header: () => "Action/Check Item",
                // accessorFn: (row) => row.item.name,
                accessorFn: (row) => row.item?.name ?? null,
                size: 340,
                cell: clickableCell({ modalID: checkItemListModalID }),
            },
            {
                accessorKey: "criteria",
                header: () => "Value/Criteria",
                size: 340,
            },
            {
                accessorKey: "schedule.schedule_name",
                header: "Schedule",
                accessorFn: (row) => row.schedule?.schedule_name,
                size: 340,
                cell: clickableCell({
                    modalID: scheduleModalID,
                    deletable: true,
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
        []
    );

    const { table, data, setData, editedRows, setEditedRows } =
        useEditableTable(checklistItems || [], columns);

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

    console.log("🚀 ~ ChecklistList ~ checkItems:", checkItems);
    console.log("🚀 ~ ChecklistList ~ schedules:", schedules);

    const goToPageCheckItem = (page) => {
        fetchCheckItems({
            search: checkItemSearchInput,
            page: page,
            perPage: perPageF3RawPackage,
        });
    };

    const handleCheckItemsSearchChange = useCallback((searchValue) => {
        fetchCheckItems({
            search: searchValue,
            page: 1,
            perPage: perPageF3RawPackage,
        });
        setCheckItemSearchInput(searchValue);
    }, []);

    const refresh = () => {
        router.reload();
    };

    const handleDelete = async () => {
        try {
            await mutate(route("api.checklist-items.massGenocide"), {
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
        console.log("🚀 ~ handleEditedItemClick ~ row:", row);
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
            await mutate(route("api.checklist-items.bulkUpdate"), {
                method: "PATCH",
                body: editedRows,
            });
            checklistItemsFetch();
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
        if (!checklists || checklists?.length === 0) {
            return;
        }

        setSelectedChecklist(checklists[0] || null);
    }, [checklists]);

    React.useEffect(() => {
        console.log(
            "🚀 ~ change in c h e c k l i s t i t e m s:",
            checklistItems
        );
        const rows = checklistItems || [];
        setData(rows);

        const map = {};
        rows.forEach((row) => {
            map[row.id] = row;
        });
        setOriginalData(map);
        setEditedRows({});
    }, [checklistItems]);

    React.useEffect(() => {
        checklistItemsFetch();
        return () => {
            checklistItemsAbort();
        };
    }, [selectedChecklist]);

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

    const handleAddNewChecklistItem = () => {
        setData((prevData) => {
            return [
                ...prevData,
                {
                    id: `new-${table.getRowCount() + 1}`,
                    name: null,
                    checklist_id: selectedChecklist?.id,
                    criteria: null,
                    schedule: null,
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
                    name: null,
                    checklist_id: selectedChecklist?.id,
                    criteria: null,
                    schedule: null,
                    created_at: null,
                    updated_at: null,
                },
            };
        });
    };

    return (
        <div>
            <div className="w-full">
                <div className="flex gap-2 sticky right-0 mb-4">
                    <button
                        className="btn btn-primary"
                        onClick={handleAddNewChecklistItem}
                    >
                        <FaPlus className="mr-2" />
                        Add New Checklist Item
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
                </div>

                <div className="flex gap-4">
                    <MultiSelectSearchableDropdown
                        options={
                            checklists?.map((checklist) => ({
                                value: checklist.name,
                                label: checklist.form_control_no,
                                original: checklist,
                            })) || []
                        }
                        onChange={(value) => {
                            console.log("🚀 ~ ChecklistList ~ value:", value);
                            setSelectedChecklist(value[0]);
                        }}
                        returnKey="original"
                        defaultSelectedOptions={
                            selectedChecklist?.name
                                ? [selectedChecklist.name]
                                : []
                        }
                        controlledSelectedOptions={
                            selectedChecklist?.name
                                ? [selectedChecklist.name]
                                : []
                        }
                        customButtonLabel={({ selectedOptions }) => {
                            return (
                                <div>
                                    {selectedOptions.length > 0 ? (
                                        <div className="flex items-center justify-between w-full">
                                            <h1 className="w-full text-lg">
                                                Checklist of{" "}
                                                {selectedOptions[0]}
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
                        buttonSelectorClassName="w-full h-auto btn-soft btn-primary text-left"
                        singleSelect
                    />
                    <button
                        className="btn btn-primary"
                        onClick={handleAddNewChecklist}
                    >
                        <GoChecklist className="w-6 h-6" />
                        Add New Checklist
                    </button>
                </div>

                <TanstackTable
                    table={table}
                    isTableLoading={isChecklistItemsLoading}
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
                    modalId={checkItemListModalID}
                    options={
                        checkItems?.checkItems.data?.map((item) => ({
                            value: String(item.name),
                            label: null,
                            original: item,
                        })) || []
                    }
                    onChange={handleEditModalSelect}
                    onSearchChange={handleCheckItemsSearchChange}
                    links={checkItems?.checkItems?.links || null}
                    currentPage={checkItems?.checkItems?.current_page || 1}
                    goToPage={goToPageCheckItem}
                    itemName="Check Item List"
                    isLoading={isLoadingCheckItems}
                    prompt="Select Check Item"
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
                    paginated={true}
                    disableSearch={false}
                    {...commonEditModalConfig}
                />
            </div>
        </div>
    );
};

export default ChecklistList;
