import PieChartWithNeedle from "@/Components/Chart/Speedometer";
import formatPastDateTimeLabel from "@/Utils/formatPastDateTimeLabel";
import { Head, usePage } from "@inertiajs/react";
import clsx from "clsx";
import { useState } from "react";
import { Tooltip } from "react-tooltip";

// ─── Color tokens (should match your CSS variables) ──────────────────────────
const statusColors = {
	ok: "var(--color-ok)",
	warning: "var(--color-warning)",
	danger: "var(--color-danger)",
};

// ─── Validity helpers ─────────────────────────────────────────────────────────
function parseRunningHours(raw) {
	if (raw === null || raw === undefined || raw === "") return null;
	const n = Number(raw);
	if (isNaN(n)) return null; // only non-numeric strings are invalid
	return n;
}

// ─── Status badge sub-component ───────────────────────────────────────────────
function ScheduleBadge({ isNoSchedule, isDue }) {
	if (isNoSchedule) {
		return (
			<span className="inline-flex items-center gap-1 text-xs px-1.5 py-0.5 bg-base-200 text-base-content/50">
				<span className="text-[10px]">—</span> no schedule
			</span>
		);
	}

	if (isDue) {
		return (
			<span className="inline-flex items-center gap-1 text-xs px-1.5 py-0.5 text-red-600 animate-pulse">
				<span>⚠</span> overdue
			</span>
		);
	}

	return null;
}

// ─── Single speedometer card ──────────────────────────────────────────────────
function SpeedometerCard({ entry, speedometerData, maxValue }) {
	const { is_no_schedule, is_due } = entry;
	const hours = parseRunningHours(entry.item_status);
	const isInvalid = hours === null;
	const needleValue = isInvalid ? 0 : Math.min(hours, maxValue);
	const tooltipId = `tooltip-${entry.asset_name.replace(/\s+/g, "-")}`;

	const checkedBy = entry.checked_by ?? {};
	const checkerName =
		[checkedBy.FIRSTNAME, checkedBy.LASTNAME].filter(Boolean).join(" ") || "—";
	const checkerTitle = checkedBy.JOB_TITLE || "—";
	const checkedAt = entry.checked_at
		? new Date(entry.checked_at).toLocaleString()
		: "—";

	const getColorBasedOnStatus = () => {
		let cumulative = 0;
		for (const range of speedometerData) {
			cumulative += range.value;
			if (hours <= cumulative) return range.fill;
		}
		return speedometerData[speedometerData.length - 1].fill;
	};

	return (
		<div
			className={clsx(
				"flex relative flex-col items-center w-30 p-1 transition-all",
				is_no_schedule && "opacity-60",
			)}
		>
			<PieChartWithNeedle
				outerRadius={30}
				innerRadius={25}
				width={100}
				cx={50}
				data={speedometerData}
				needleValue={needleValue}
				height={40}
				cy={30}
			/>

			{/* Asset name + running hours — hoverable */}
			<div data-tooltip-id={tooltipId} className="text-center cursor-default">
				<p
					className={clsx(
						`text-lg leading-5 font-bold ${isInvalid ? "text-base-content italic" : "text-gray-800"}`,
					)}
					style={{ color: getColorBasedOnStatus() }}
				>
					{isInvalid ? "invalid" : `${hours.toLocaleString()} hrs`}
				</p>
				<p className="text-xs font-semibold text-base-content truncate w-full">
					{entry.asset_name}
				</p>

				<div className="absolute top-0 left-0 mt-1">
					<ScheduleBadge isNoSchedule={is_no_schedule} isDue={is_due} />
				</div>
			</div>

			{/* Tooltip */}
			<Tooltip id={tooltipId} place="top" className="z-50 max-w-xs">
				<div className="text-xs space-y-1">
					{!!is_due && (
						<p className="text-red-400 font-semibold">
							⚠ Running hours are overdue for update
						</p>
					)}
					{!!is_no_schedule && (
						<p className="text-gray-400 italic">
							No maintenance schedule configured
						</p>
					)}
					<p className="text-sm font-semibold text-base-content truncate w-full">
						<span>{entry.asset_name}</span>
						<span className="text-xs opacity-50 text-base-content">
							@{entry.asset_location}
						</span>
					</p>

					<p className="font-semibold">
						encoded by: {checkerName} ({checkerTitle})
					</p>
					<p className="text-white">max running hours: {maxValue}</p>
					<p className="text-white">
						Checked{" "}
						<span className="font-bold opacity-100">
							{formatPastDateTimeLabel(checkedAt, true)}
						</span>{" "}
						({checkedAt})
					</p>
				</div>
			</Tooltip>
		</div>
	);
}

// ─── Group section (Vacuum / Air Compressor) ──────────────────────────────────
function SpeedometerGroup({ title, entries, speedometerData, maxValue }) {
	return (
		<section className="border border-base-content/10 p-2">
			<h2 className="text-base-content">{title}</h2>
			<div className="flex gap-2">
				{entries.map((entry, i) => (
					<SpeedometerCard
						key={`${entry.asset_name}-${i}`}
						entry={entry}
						speedometerData={speedometerData}
						maxValue={maxValue}
					/>
				))}
			</div>
		</section>
	);
}

const CATEGORIES = [
	{
		key: "assets_complete",
		label: "Complete",
		description: "All due items checked",
		color: "text-emerald-600",
		bg: "bg-emerald-500/10",
		border: "border-emerald-200/50",
		indicator: "bg-emerald-500",
		barColor: "bg-emerald-400",
		icon: (
			<svg viewBox="0 0 20 20" fill="currentColor" className="w-5 h-5">
				<path
					fillRule="evenodd"
					d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
					clipRule="evenodd"
				/>
			</svg>
		),
	},
	{
		key: "assets_partial",
		label: "Partial",
		description: "Some items still due",
		color: "text-amber-600",
		bg: "bg-amber-500/10",
		border: "border-amber-200/50",
		indicator: "bg-amber-500",
		barColor: "bg-amber-400",
		icon: (
			<svg viewBox="0 0 20 20" fill="currentColor" className="w-5 h-5">
				<path
					fillRule="evenodd"
					d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
					clipRule="evenodd"
				/>
			</svg>
		),
	},
	{
		key: "assets_not_started",
		label: "Not Started",
		description: "No items checked yet",
		color: "text-red-600",
		bg: "bg-red-500/10",
		border: "border-red-200/50",
		indicator: "bg-red-500",
		barColor: "bg-red-400",
		icon: (
			<svg viewBox="0 0 20 20" fill="currentColor" className="w-5 h-5">
				<path
					fillRule="evenodd"
					d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
					clipRule="evenodd"
				/>
			</svg>
		),
	},
	{
		key: "assets_overdue",
		label: "Overdue",
		description: "Due date has passed",
		color: "text-slate-500",
		bg: "bg-slate-500/10",
		border: "border-slate-200/50",
		indicator: "bg-slate-400",
		barColor: "bg-slate-300",
		icon: (
			<svg viewBox="0 0 20 20" fill="currentColor" className="w-5 h-5">
				<path
					fillRule="evenodd"
					d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.75 9.25a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5z"
					clipRule="evenodd"
				/>
			</svg>
		),
	},
];

// ─── Asset row inside expanded list ──────────────────────────────────────────
function AssetRow({ asset, color, barColor }) {
	const due = parseInt(asset.due_items) || 0;
	const done = parseInt(asset.done_items) || 0;
	const overdue = parseInt(asset.overdue_items) || 0;
	const total = due + done;
	const pct = total > 0 ? Math.round((done / total) * 100) : 0;

	return (
		<div className="flex items-center gap-3 py-2 px-1 hover:bg-black/5 transition-colors">
			<div className="flex-1 min-w-0">
				<div className="flex items-center gap-1">
					<span className="text-sm font-semibold text-base-content truncate">
						{asset.code}
					</span>
					{overdue > 0 && (
						<span className="text-[10px] font-light px-1.5 py-0.5  text-red-600 shrink-0">
							{overdue} overdue
						</span>
					)}
				</div>
				<p className="text-xs text-base-content/50 truncate">
					{asset.location_name}
				</p>
			</div>

			<div className="flex items-center gap-1 shrink-0">
				{total > 0 && (
					<div className="flex items-center gap-1.5">
						<div className="w-16 h-1.5 rounded-full bg-black/10 overflow-hidden">
							<div
								className={clsx("h-full rounded-full transition-all", barColor)}
								style={{ width: `${pct}%` }}
							/>
						</div>
					</div>
				)}
				<div className="flex flex-col items-end min-w-10">
					<div className={clsx("text-xs font-medium tabular-nums", color)}>
						{done}/{total}
					</div>
					<div className="text-[11px] text-base-content/50 w-8 text-right">
						{pct}%
					</div>
				</div>
			</div>
		</div>
	);
}

// ─── Single category card ─────────────────────────────────────────────────────
function CategoryCard({ category, assets = [] }) {
	const [expanded, setExpanded] = useState(false);
	const count = assets.length;

	return (
		<div
			className={clsx(
				"border overflow-hidden transition-all duration-200",
				category.border,
				expanded ? "shadow-md" : "shadow-sm hover:shadow-md",
			)}
		>
			{/* Header */}
			<button
				onClick={() => count > 0 && setExpanded((p) => !p)}
				className={clsx(
					"w-full flex items-center gap-4 p-4 transition-colors text-left",
					category.bg,
					count > 0 ? "cursor-pointer" : "cursor-default",
				)}
			>
				{/* Left indicator bar */}
				<div
					className={clsx(
						"w-1 self-stretch rounded-full shrink-0",
						category.indicator,
					)}
				/>

				{/* Icon */}
				<div className={clsx("shrink-0", category.color)}>{category.icon}</div>

				{/* Text */}
				<div className="flex-1 min-w-0">
					<p className={clsx("text-sm font-semibold", category.color)}>
						{category.label}
					</p>
					<p className="text-xs text-base-content/50">{category.description}</p>
				</div>

				{/* Count */}
				<div className="flex items-center gap-2 shrink-0">
					<span
						className={clsx(
							"text-2xl font-bold tabular-nums leading-none",
							count === 0 ? "text-base-content/30" : category.color,
						)}
					>
						{count}
					</span>
					{count > 0 && (
						<svg
							viewBox="0 0 20 20"
							fill="currentColor"
							className={clsx(
								"w-4 h-4 transition-transform duration-200 text-base-content/30",
								expanded && "rotate-180",
							)}
						>
							<path
								fillRule="evenodd"
								d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
								clipRule="evenodd"
							/>
						</svg>
					)}
				</div>
			</button>

			{/* Expanded asset list */}
			{expanded && count > 0 && (
				<div className="border-t border-black/5 px-2 py-1 max-h-60 overflow-y-auto">
					{assets.map((asset) => (
						<AssetRow
							key={asset.id}
							asset={asset}
							color={category.color}
							barColor={category.barColor}
						/>
					))}
				</div>
			)}
		</div>
	);
}

// ─── Main component ───────────────────────────────────────────────────────────
function AssetDueCategories({ assets_due = mockData }) {
	const total = CATEGORIES.reduce(
		(sum, cat) => sum + (assets_due[cat.key]?.length ?? 0),
		0,
	);

	return (
		<section className="space-y-2">
			<div className="flex items-baseline gap-2">
				<h2 className="text-base font-semibold text-base-content">
					Checklist Status
				</h2>
				<span className="text-xs text-base-content/40">
					{total} assets total
				</span>
			</div>

			<div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
				{CATEGORIES.map((category) => (
					<CategoryCard
						key={category.key}
						category={category}
						assets={assets_due[category.key] ?? []}
					/>
				))}
			</div>
		</section>
	);
}

// ─── Dashboard ────────────────────────────────────────────────────────────────
export default function Dashboard() {
	const {
		vacuum_latest_running_hours,
		air_compressor_latest_running_hours,
		vacuum_running_hours_ok,
		assets_due,
		vacuum_running_hours_warning,
		vacuum_running_hours_danger,
		air_compressor_running_hours_ok,
		air_compressor_running_hours_warning,
		air_compressor_running_hours_danger,
	} = usePage().props;
	console.log("🚀 ~ Dashboard ~ assets_due:", assets_due);
	console.log(
		"🚀 ~ Dashboard ~ vacuum_latest_running_hours:",
		vacuum_latest_running_hours,
	);
	console.log(
		"🚀 ~ Dashboard ~ air_compressor_latest_running_hours:",
		air_compressor_latest_running_hours,
	);

	const vacuumMax =
		vacuum_running_hours_ok +
		vacuum_running_hours_warning +
		vacuum_running_hours_danger;
	const airCompressorMax =
		air_compressor_running_hours_ok +
		air_compressor_running_hours_warning +
		air_compressor_running_hours_danger;

	const vacuumSpeedometer = [
		{ name: "ok", value: vacuum_running_hours_ok, fill: statusColors.ok },
		{
			name: "warning",
			value: vacuum_running_hours_warning,
			fill: statusColors.warning,
		},
		{
			name: "danger",
			value: vacuum_running_hours_danger,
			fill: statusColors.danger,
		},
	];

	const airCompressorSpeedometer = [
		{
			name: "ok",
			value: air_compressor_running_hours_ok,
			fill: statusColors.ok,
		},
		{
			name: "warning",
			value: air_compressor_running_hours_warning,
			fill: statusColors.warning,
		},
		{
			name: "danger",
			value: air_compressor_running_hours_danger,
			fill: statusColors.danger,
		},
	];

	return (
		<>
			<Head title="Dashboard" />

			<div className="space-y-4">
				<h1 className="text-2xl font-bold text-base-content">Dashboard</h1>

				<AssetDueCategories assets_due={assets_due} />

				<SpeedometerGroup
					title="Vacuum Running Hours"
					entries={vacuum_latest_running_hours}
					speedometerData={vacuumSpeedometer}
					maxValue={vacuumMax}
				/>

				<SpeedometerGroup
					title="Air Compressor Running Hours"
					entries={air_compressor_latest_running_hours}
					speedometerData={airCompressorSpeedometer}
					maxValue={airCompressorMax}
				/>
			</div>
		</>
	);
}
