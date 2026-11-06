import MultiSelectSearchableDropdown from "@/Components/MultiSelectSearchableDropdown";
import STATUS_CONFIG from "@/Constants/checkItemStatusConfig";
import formatFriendlyDate from "@/Utils/formatFriendlyDate";
import { router, usePage } from "@inertiajs/react";
import React, { useEffect, useState, useRef } from "react";
import { FaCaretDown } from "react-icons/fa6";
import SearchInput from "./SearchInput";

const checklistModalID = "asset-health-checklist-modal";

const LatestResultsCards = ({ latestResults }) => {
	return (
		<div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 overflow-y-auto">
			{Object.entries(latestResults).map(([assetKey, results]) => {
				const assetName = results[0]?.asset_name ?? assetKey;
				const location = results[0]?.asset_location;

				return (
					<div
						key={assetKey}
						className="card shadow border border-base-content/20 flex flex-col gap-2"
					>
						<h2 className="font-bold text-lg bg-base-100 p-2">
							{assetName}
							{location && (
								<span className="opacity-50 font-light">@{location}</span>
							)}
						</h2>
						<div className="flex flex-col gap-1 p-2">
							{results.map((result, idx) => {
								const itemName = result?.item_name;
								const rawStatus = result?.item_status;
								const config = STATUS_CONFIG[rawStatus?.toLowerCase()];
								const Icon = config?.icon;
								const checkedBy = result?.checked_by ?? {};
								const checkedAt = result?.checked_at;

								return (
									<div
										key={`${itemName}-${idx}`}
										className={`flex justify-between items-center px-2 py-1 ${config?.bgClass ?? "bg-gray-100/5"}`}
									>
										<div className="text-sm">
											<div>{itemName}</div>
											<div className="text-xs opacity-50">
												by {checkedBy?.FIRSTNAME}
												<span className="ml-1">
													at {formatFriendlyDate(checkedAt, true)}
												</span>
											</div>
										</div>
										<div className="flex items-center gap-1">
											<span className="text-xs font-medium">
												{config?.label ?? rawStatus ?? "—"}
											</span>
											<span
												className="drop-shadow-xl/25"
												style={{ color: config?.color ?? "#9ca3af" }}
											>
												{Icon && <Icon size={16} />}
											</span>
										</div>
									</div>
								);
							})}
						</div>
					</div>
				);
			})}
		</div>
	);
};

const AssetHealthBoardPage = () => {
	const {
		checklists,
		selectedChecklist,
		latestResults,
		search: serverSearch,
	} = usePage().props;
	console.log("🚀 ~ AssetHealthBoardPage ~ checklists:", checklists);
	console.log(
		"🚀 ~ AssetHealthBoardPage ~ selectedChecklist:",
		selectedChecklist,
	);
	console.log("🚀 ~ AssetHealthBoardPage ~ latestResults:", latestResults);
	const checklistItems = selectedChecklist?.checklist_items || [];

	const total = Object.entries(latestResults || {}).length;

	const [searchInput, setSearchInput] = useState(serverSearch || "");

	const didMountRef = useRef(false);
	useEffect(() => {
		if (!didMountRef.current) {
			didMountRef.current = true;
			return;
		}
		const timer = setTimeout(() => {
			router.reload({
				data: {
					search: searchInput,
					page: 1,
				},
				preserveState: true,
				preserveScroll: true,
			});
		}, 700);

		return () => clearTimeout(timer);
	}, [searchInput]);

	const reload = (selectedChecklistID) => {
		router.reload({
			data: {
				checklist_id: selectedChecklistID,
			},
			preserveState: true,
			preserveScroll: true,
		});
	};

	return (
		<div className="flex w-full h-[calc(100vh-100px)] flex-col gap-4 relative p-1 shadow-lg">
			<h1 className="font-bold">Asset Health Board</h1>
			<MultiSelectSearchableDropdown
				modalId={checklistModalID}
				options={
					checklists?.map((checklist) => ({
						value: checklist.name,
						original: checklist,
					})) || []
				}
				returnKey="original"
				onChange={(value) => {
					reload(value[0]?.id);
				}}
				defaultSelectedOptions={[
					...[checklists.find((r) => r.id === selectedChecklist?.id)?.name].filter(Boolean),
				]}
				controlledSelectedOptions={[
					...[checklists.find((r) => r.id === selectedChecklist?.id)?.name].filter(Boolean),
				]}
				customButtonLabel={({ selectedOptions }) => {
					return (
						<div>
							{selectedOptions.length > 0 ? (
								<div className="flex items-center justify-between w-full">
									<h1 className="w-full text-lg">{selectedOptions[0]}</h1>
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
				itemName="Checklist List"
				prompt="Select Checklist"
				contentClassName="h-50"
				buttonSelectorClassName="w-full min-h-8 h-auto btn-soft btn-primary text-left"
				singleSelect
			/>

			<div className="flex">
				<div className="ml-2 flex items-center justify-between">
					<span className="text-sm">{total} results</span>
				</div>
				<SearchInput
					inputClassName="w-100"
					placeholder="search by asset name"
					initialSearchInput={searchInput}
					onSearchChange={setSearchInput}
				/>
			</div>

			<LatestResultsCards
				latestResults={latestResults || {}}
				checklistItems={checklistItems}
			/>
		</div>
	);
};

export default AssetHealthBoardPage;
