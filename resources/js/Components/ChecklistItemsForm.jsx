import { useMutation } from "@/Hooks/useMutation";
import { formatTimestamp } from "@/Utils/formatISOTimestampToDate";
import clsx from "clsx";
import { useEffect } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import toast from "react-hot-toast";
import { TbAlertCircle } from "react-icons/tb";

export default function ChecklistItemsForm({
    assetId,
    checklistId,
    items,
    isItemsLoading,
    onValid = () => {},
    onAnyFilled = () => {},
    onSubmit: onSubmitProp,
}) {
    console.log("🚀 ~ ChecklistItemsForm ~ checklistId:", checklistId);
    console.log("🚀 ~ ChecklistItemsForm ~ assetId:", assetId);
    console.log("🚀 ~ ChecklistItemsForm ~ items:", items);
    const { mutate, isLoading } = useMutation();

    const {
        register,
        control,
        handleSubmit,
        reset,
        watch,
        formState: { errors, isValid },
    } = useForm({
        defaultValues: {
            items: items.map((item) => ({
                checklist_item_id: item.id,
                item_status: "",
                remarks: "",
            })),
            notes: "",
        },
    });

    const { fields } = useFieldArray({
        control,
        name: "items",
    });

    const isAllDone = items.every((item) => item.is_due === 0);

    const watchItems = watch("items");

    console.log("🚀 ~ ChecklistItemsForm ~ watchItems:", watchItems);

    const hasAnyValue = watchItems.some(
        (item) => item.item_status && item.item_status.trim() !== "",
    );

    const onSubmit = async (data) => {
        try {
            await mutate(route("api.checklist-item-result.recordResult"), {
                body: {
                    asset_id: assetId,
                    checklist_id: checklistId,
                    notes: data.notes,
                    items: data.items,
                },
            });

            if (typeof onSubmitProp === "function") {
                onSubmitProp();
            }
            // router.reload();
        } catch (error) {
            console.error(error);
            toast.error(error?.message);
        }
    };

    useEffect(() => {
        console.log("🚀 ~ ChecklistItemsForm ~ hasAnyValue:", hasAnyValue);

        onAnyFilled(hasAnyValue);
    }, [hasAnyValue]);

    useEffect(() => {
        console.log("🚀 ~ ChecklistItemsForm ~ isValid:", isValid);
        onValid(isValid);
    }, [isValid, onValid]);

    // useEffect(() => {
    //     if (items.length) {
    //         reset({
    //             items: items.map((item) => ({
    //                 checklist_item_id: item.id,
    //                 item_status: "",
    //                 remarks: "",
    //             })),
    //             notes: "",
    //         });
    //     }
    // }, [items, reset]);

    if (isItemsLoading) {
        return (
            <div className="w-full h-100 skeleton flex justify-center items-center">
                <span className="loading loading-spinner"></span>
            </div>
        );
    }

    return (
        <form onSubmit={handleSubmit(onSubmit)} className="flex-1 flex flex-col justify-between">
            <div>
                {isAllDone && (
                    <div
                        role="alert"
                        className="text-warning-content bg-warning mt-2 alert alert-outline alert-warning"
                    >
                        <TbAlertCircle className="w-6 h-6" />
                        All Items on this checklist are already done
                    </div>
                )}
                <div className="divider"></div>
                <div className="mt-4 mb-2 flex items-center">
                    <div className="flex-2 font-semibold">
                        Due Items to be checked
                    </div>
                    <div className="flex-1 text-right px-2">Criteria</div>
                    <div className="flex-1 input cursor-default border-0">
                        Status
                    </div>
                    <div className="flex-1 input cursor-default border-0">
                        Remarks
                    </div>
                    <div className="flex-1 px-2 opacity-50">Last Checked</div>
                </div>
                <div>
                    {fields.map((field, index) => {
                        // const isDisabled = !items[index].is_due;
                        const isDisabled = false;
                        const name = items[index].name;
                        const scheduleName = items[index].schedule_name;
                        const criteria = items[index].criteria;
                        const checkedAt = items[index].checked_at;
                        const isDue = items[index].is_due;
                        const isVerified = Boolean(items[index].verified_by);

                        return (
                            <div
                                className={clsx("flex items-center", {
                                    "bg-base-200": index % 2 === 0,
                                })}
                                key={field.id}
                            >
                                <label className="flex-2">
                                    <span>{name}</span>
                                    <span className="ml-1 opacity-50">
                                        ({scheduleName})
                                    </span>
                                </label>

                                <div className="flex-1 text-right px-2">
                                    <span className="">{criteria}</span>
                                </div>

                                {/* hidden but submitted */}
                                <input
                                    className="input bg-transparent"
                                    type="hidden"
                                    {...register(`items.${index}.checklist_item_id`)}
                                />

                                <input
                                    disabled={isDisabled}
                                    className="input bg-transparent flex-1"
                                    {...register(`items.${index}.item_status`, {
                                        // required: !isDisabled && "Status is required",
                                    })}
                                />

                                <input
                                    disabled={isDisabled}
                                    className="input bg-transparent flex-1"
                                    {...register(`items.${index}.remarks`, {})}
                                />

                                <div className="px-2 flex-1 flex gap-2 items-center">
                                    {/* disable all done */}
                                    {isDue ? (
                                        <>
                                            <div className="opacity-50 text-xs">
                                                Due
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            <div className="opacity-50 text-xs">
                                                {isDue
                                                    ? "Due"
                                                    : `${formatTimestamp(checkedAt)}`}
                                            </div>
                                            {/* <div className="text-green-500">
                                                <FaCheckCircle size={18} />
                                            </div> */}
                                        </>
                                    )}
                                    {errors.items?.[index]?.item_status && (
                                        <p className="text-warning">
                                            {errors.items[index].item_status.message}
                                        </p>
                                    )}
                                    {errors.items?.[index]?.remarks && (
                                        <p className="text-warning">
                                            {errors.items[index].remarks.message}
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            <div className="flex flex-col w-full">
                <div className="font-semibold">Remarks</div>
                <textarea
                    className="mt-2 textarea w-full"
                    {...register("notes", {
                        maxLength: {
                            value: 500,
                            message: "Maximum 500 characters",
                        },
                    })}
                />

                <button
                    className="btn btn-primary mt-4"
                    type="submit"
                    disabled={isLoading}
                >
                    Submit
                </button>
            </div>
        </form>
    );
}
