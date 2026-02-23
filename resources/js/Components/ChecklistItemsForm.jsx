import { useMutation } from "@/Hooks/useMutation";
import { formatTimestamp } from "@/Utils/formatISOTimestampToDate";
import clsx from "clsx";
import { useEffect } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import toast from "react-hot-toast";
import { FaCheckCircle } from "react-icons/fa";
import { GoAlert } from "react-icons/go";
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
	const { mutate, isLoading, errorMessage } = useMutation();

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

			toast.success("Checklist submitted successfully!");
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

	if (isItemsLoading) {
		return (
			<div className="w-full h-100 skeleton flex justify-center items-center">
				<span className="loading loading-spinner"></span>
			</div>
		);
	}

	return (
		<form
			onSubmit={handleSubmit(onSubmit)}
			className="flex-1 flex flex-col justify-between"
		>
			{isAllDone && (
				<div
					role="alert"
					className="text-success-content bg-success mt-2 p-1 alert alert-outline alert-success"
				>
					<TbAlertCircle className="w-6 h-6" />
					All Items on this checklist are already done
				</div>
			)}
			<div className="h-125 overflow-y-auto">
				<div className="mt-4 mb-2 flex items-center">
					<div className="flex-2 font-semibold">Due Items to be checked</div>
					<div className="flex-1">Criteria</div>
					<div className="flex-1 input cursor-default border-0">Status</div>
					<div className="flex-1 input cursor-default border-0">Remarks</div>
					<div className="flex-1 opacity-50">Last Checked</div>
					<div className="flex-1 opacity-50 text-right">Checked | Verified</div>
				</div>
				<div>
					{fields.map((field, index) => {
						// const isDisabled = !items[index].is_due;
						const isDisabled = false;
						const name = items[index].name;
						const scheduleName = items[index].schedule_name;
						const criteria = items[index].criteria;
						const checkedAt = items[index].checked_at;
						const createdBy = items[index].created_by;
						const verifiedBy = items[index].verified_by;
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
									<span className="ml-1 opacity-50">({scheduleName})</span>
								</label>

								<div className="flex-1">
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

								<div className="flex-1 flex gap-1 items-center">
									<div className="opacity-50 text-xs">
										{formatTimestamp(checkedAt)}
									</div>

									<div className="text-xs">
										{isDue ? (
											<span className="text-yellow-600">due</span>
										) : (
											<FaCheckCircle size={8} className="text-green-600" />
										)}
									</div>

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
								<div className="text-right flex-1 text-xs opacity-75 flex justify-end gap-1">
									<span>{createdBy?.FIRSTNAME || "unknown"}</span>
									<span className="opacity-50">|</span>
									<span>{verifiedBy?.FIRSTNAME || "unknown"}</span>
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

				{errorMessage && (
					<div className="text-error p-1 border border-error mt-1">
						<GoAlert /> {errorMessage}
					</div>
				)}

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
