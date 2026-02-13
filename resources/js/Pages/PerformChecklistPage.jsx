import ChecklistItemsForm from "@/Components/ChecklistItemsForm";
import MultiSelectSearchableDropdown from "@/Components/MultiSelectSearchableDropdown";
import Steps from "@/Components/Steps";
import { useFetch } from "@/Hooks/useFetch";
import { useChecklistStore } from "@/Store/checklistStore";
import React, { useEffect, useState } from "react";

const performChecklistSteps = [
    "Pick a checklist",
    "Select an Equipment",
    "Fill up the items",
    "Review & Submit",
];

const assetModalID = "perform-checklist-assets-modal";
const checklistModalID = "perform-checklist-checklist-modal";
const maxItem = -1; // all

const PerformChecklistPage = () => {
    const {
        data: checklists,
        isLoading,
        fetchChecklists,
    } = useChecklistStore();

    const {
        data: assets,
        isLoading: isLoadingAsset,
        errorMessage: errorMessageAssets,
        errorData: errorDataAssets,
        cancel: cancelAssets,
        fetch: fetchAssets,
    } = useFetch(route("api.checklist-assets.due-assets"), {
        auto: false,
    });

    const {
        data: checklistItems,
        isLoading: isLoadingChecklistItems,
        errorMessage: errorMessageChecklistItems,
        errorData: errorDataChecklistItems,
        cancel: cancelChecklistItems,
        fetch: fetchChecklistItems,
    } = useFetch(route("api.checklist-items.scheduled-check-items"), {
        auto: false,
    });

    const [assetSearchInput, setAssetSearchInput] = useState("");

    const [currentStep, setCurrentStep] = React.useState(0);
    const [selectedChecklist, setSelectedChecklist] = React.useState(null);
    const [selectedAssets, setSelectedAssets] = React.useState([]);
    const [isFormFilled, setIsFormFilled] = React.useState(false);

    useEffect(() => {
        if (selectedChecklist) setCurrentStep(1);
        if (selectedChecklist && selectedAssets.length > 0) setCurrentStep(2);
        if (isFormFilled) setCurrentStep(3);
    }, [selectedChecklist, selectedAssets, isFormFilled]);

    const handleAssetsChange = (asset) => {
        setSelectedAssets(asset);
    };

    useEffect(() => {
        setSelectedAssets([]);
        setAssetSearchInput("");

        if (selectedChecklist) {
            fetchAssets({
                search: assetSearchInput,
                checklistId: selectedChecklist?.id,
                perPage: maxItem,
            });
        }
    }, [selectedChecklist]);

    useEffect(() => {
        fetchChecklistItems({
            assetId: selectedAssets[0]?.id,
            checklistId: selectedChecklist?.id,
        });
    }, [selectedAssets]);

    const onSubmit = () => {
        fetchAssets({
            search: assetSearchInput,
            checklistId: selectedChecklist?.id,
            perPage: maxItem,
        });
    };

    return (
        <div className="flex gap-4 relative">
            <div className="h-30 sticky top-0">
                <Steps
                    steps={performChecklistSteps}
                    currentStep={currentStep}
                />
            </div>

            <div className="flex-1 flex flex-col bg-base-100 h-[calc(100vh-100px)] p-4 shadow-2xl">
                <MultiSelectSearchableDropdown
                    modalId={checklistModalID}
                    options={
                        checklists?.checklistArray?.map((checklist) => ({
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
                                        <h1 className="flex gap-2 items-center w-full text-lg">
                                            <span className="text-sm font-normal text-base-content">
                                                Performing
                                            </span>
                                            <span>{selectedOptions[0]}</span>
                                        </h1>
                                    </div>
                                ) : (
                                    "Select a checklist to perform"
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
                    buttonSelectorClassName="min-h-8 w-full h-auto btn-soft btn-primary text-left"
                    singleSelect
                />

                {selectedChecklist && (
                    <MultiSelectSearchableDropdown
                        modalId={assetModalID}
                        options={
                            assets?.assets?.map((item) => ({
                                value: String(item.code),
                                label: item?.location?.location_name,
                                totalCheckItems: item?.total_items,
                                dueCheckItems: item?.due_items,
                                doneCheckItems: item?.done_items,
                                original: item,
                            })) || []
                        }
                        onChange={(value) => {
                            handleAssetsChange(value);
                        }}
                        itemName="Asset List"
                        isLoading={isLoadingAsset}
                        singleSelect
                        disableTooltip
                        disableSelectedContainer
                        customButtonLabel={({ selectedOptions }) => {
                            return (
                                <div>
                                    {selectedOptions.length > 0 ? (
                                        <div className="flex items-left justify-between w-full">
                                            <h1 className="flex gap-2 items-center w-full text-lg">
                                                <span className="text-sm font-normal text-base-content">
                                                    on equipment
                                                </span>
                                                <span>
                                                    {selectedOptions[0]}
                                                </span>
                                            </h1>
                                        </div>
                                    ) : (
                                        "Pick an Asset on this checklist"
                                    )}
                                </div>
                            );
                        }}
                        prompt="Select Asset"
                        buttonSelectorClassName={
                            "w-full min-h-8 h-auto btn-soft btn-primary text-left"
                        }
                        contentClassName={"h-100"}
                        defaultSelectedOptions={
                            selectedAssets?.name ? [selectedAssets.name] : []
                        }
                        returnKey={"original"}
                    >
                        {(option) => (
                            <div className="flex w-full justify-between text-sm">
                                <span>
                                    {option.value}
                                    <span className="opacity-60">
                                        {option.label ? `@${option.label}` : ""}
                                    </span>
                                </span>
                                <div className="text-sm">
                                    {option.dueCheckItems > 0 ? (
                                        <div className="flex gap-2">
                                            <span className="badge bg-warning/75 text-neutral badge-sm">
                                                {option.dueCheckItems} due
                                            </span>
                                            <span className="badge bg-success/75 text-neutral badge-sm">
                                                {option.doneCheckItems} done
                                            </span>
                                        </div>
                                    ) : option.doneCheckItems > 0 ? (
                                        <span className="badge bg-success/75 text-neutral badge-sm">
                                            Complete
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            No items
                                        </span>
                                    )}
                                </div>
                            </div>
                        )}
                    </MultiSelectSearchableDropdown>
                )}

                {selectedChecklist &&
                    selectedAssets.length > 0 &&
                    checklistItems.length > 0 && (
                        <ChecklistItemsForm
                            assetId={selectedAssets[0]?.id}
                            checklistId={selectedChecklist?.id}
                            items={checklistItems}
                            isItemsLoading={isLoadingChecklistItems}
                            // onValid={setIsFormFilled}
                            onAnyFilled={setIsFormFilled}
                            onSubmit={onSubmit}
                        />
                    )}
            </div>
        </div>
    );
};

export default PerformChecklistPage;
