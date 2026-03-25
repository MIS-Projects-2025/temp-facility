import RunningHoursGauge from "@/Components/Chart/RunningHoursGauge";
import formatFriendlyDate from "@/Utils/formatFriendlyDate";
import clsx from "clsx";
import { GrAlert } from "react-icons/gr";
import { Tooltip } from "react-tooltip";

function parseRunningHours(raw) {
	if (raw === null || raw === undefined || raw === "") return null;
	const n = Number(raw);
	if (isNaN(n)) return null; // only non-numeric strings are invalid
	return n;
}

function SpeedometerCard({
	assetName,
	runningHoursItem,
	powerItem,
	speedometerData = [],
	maxValue,
}) {
	console.log(
		"🚀 xxxxxxxxxxxxxxxxxxxxxx~ SpeedometerCard ~ runningHoursItem:",
		runningHoursItem,
	);
	const latest = runningHoursItem?.latest;
	const lastPmDate = runningHoursItem?.latest?.last_pm_date ?? null;
	const first = runningHoursItem?.first;

	const powerStatus = powerItem?.latest?.item_status || "unknown";
	const powerStatusLastUpdated = powerItem?.latest?.checked_at || null;

	const hours = parseRunningHours(runningHoursItem?.running_hours);
	console.log("🚀 ~ SpeedometerCard ~ hours:", hours);
	const isInvalid = runningHoursItem?.running_hours_invalid;
	console.log("🚀 ~ SpeedometerCard ~ isInvalid:", isInvalid);
	const isNoSchedule = !!latest?.is_no_schedule;
	const isDue = !!latest?.is_due;

	const tooltipId = `tooltip-${assetName.replace(/\s+/g, "-")}`;

	const okUpTo = speedometerData[0]?.value ?? maxValue * 0.6;
	const warningUpTo = okUpTo + (speedometerData[1]?.value ?? maxValue * 0.2);

	const formatChecker = (checkedBy) => {
		if (!checkedBy) return "—";
		const name =
			[checkedBy.FIRSTNAME, checkedBy.LASTNAME].filter(Boolean).join(" ") ||
			"—";
		return `${name} (${checkedBy.JOB_TITLE || "—"})`;
	};

	return (
		<div
			className={clsx("relative transition-all", isNoSchedule && "opacity-60")}
			data-tooltip-id={tooltipId}
		>
			<RunningHoursGauge
				current={isInvalid ? 0 : hours || 0}
				okUpTo={okUpTo}
				warningUpTo={warningUpTo}
				max={maxValue}
				label={assetName}
				powerStatus={powerStatus}
				isDue={isDue}
				isNoSchedule={isNoSchedule}
			/>

			{lastPmDate === null && (
				<div className="flex gap-1 text-xs text-error italic mt-1">
					<GrAlert />↑ No PM on record
				</div>
			)}

			{isInvalid && (
				<p className="text-xs italic opacity-50 mt-1">Invalid running hours</p>
			)}

			<Tooltip id={tooltipId} place="top" className="z-50 max-w-sm">
				<div className="text-xs space-y-1">
					{!!isDue && (
						<p className="text-red-400 font-semibold">
							⚠ Running hours are overdue for update
						</p>
					)}
					{!!isNoSchedule && (
						<p className="text-white italic">
							No maintenance schedule configured
						</p>
					)}
					{lastPmDate === null && (
						<div className="flex gap-1 text-xs text-error italic mt-1">
							<GrAlert /> No PM on record
						</div>
					)}

					<p className="text-sm font-semibold text-white truncate w-full">
						<span>{assetName}</span>
						<span className="text-xs opacity-50">
							{" "}
							@ {latest?.asset_location}
						</span>
					</p>

					<div className="flex gap-1">
						{powerStatus}
						<span className="opacity-50">
							last checked{" "}
							{formatFriendlyDate(powerStatusLastUpdated, true) || "—"}
						</span>
					</div>

					<p className="text-white">max running hours: {maxValue}</p>

					<div className="border-t border-white/20 pt-1 mt-1 space-y-1">
						<p className="font-semibold text-white">
							First check after last PM
						</p>
						<p>
							Hours:{" "}
							<span className="font-bold">{first?.item_status ?? "—"}</span>
						</p>
						<p>
							Checked:{" "}
							{first?.checked_at
								? new Date(first.checked_at).toLocaleString()
								: "—"}
						</p>
						<p>By: {formatChecker(runningHoursItem?.first_checked_by)}</p>
					</div>

					<div className="border-t border-white/20 pt-1 mt-1 space-y-1">
						<p className="font-semibold text-white">Latest check</p>
						<p>
							Hours:{" "}
							<span className="font-bold">{latest?.item_status ?? "—"}</span>
						</p>
						<p>
							Checked:{" "}
							{latest?.checked_at
								? new Date(latest.checked_at).toLocaleString()
								: "—"}
						</p>
						<p>By: {formatChecker(runningHoursItem?.latest_checked_by)}</p>
					</div>
				</div>
			</Tooltip>
		</div>
	);
}

export default function SpeedometerGroup({
	title,
	entries,
	runningHoursName,
	speedometerData,
	maxValue,
}) {
	console.log("🚀 ~ SpeedometerGroup ~ runningHoursName:", runningHoursName);
	return (
		<section>
			<h2 className="text-base-content text-center border-b border-b-base-content/20 mb-2">
				{title}{" "}
				{entries?.length === 0 && (
					<span className="opacity-50">(no entries)</span>
				)}
			</h2>
			<div className="flex flex-col gap-2">
				{Object.entries(entries).map(([assetName, items]) => {
					console.log("🚀 ~ SpeedometerGroup ~ items:", items);

					return (
						<SpeedometerCard
							key={assetName}
							assetName={assetName}
							runningHoursItem={items[runningHoursName] ?? null}
							powerItem={items["Vacuum Pump"] ?? null}
							speedometerData={speedometerData}
							maxValue={maxValue}
						/>
					);
				})}
			</div>
		</section>
	);
}