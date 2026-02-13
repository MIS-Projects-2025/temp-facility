import Dropdown from "@/Components/sidebar/Dropdown";
import SidebarLink from "@/Components/sidebar/SidebarLink";
import { usePage } from "@inertiajs/react";
import { BiTask } from "react-icons/bi";
import { FaGasPump, FaListAlt, FaTools, FaTrash } from "react-icons/fa";
import {
	FaBiohazard,
	FaCubes,
	FaFireExtinguisher,
	FaLocationDot,
	FaPlay,
} from "react-icons/fa6";
import { GiChemicalDrop, GiWaterTank } from "react-icons/gi";
import { IoSettingsOutline, IoWaterSharp } from "react-icons/io5";
import { LuLayoutDashboard } from "react-icons/lu";
import {
	MdAir,
	MdChecklist,
	MdFireHydrantAlt,
	MdHealthAndSafety,
	MdPropaneTank,
	MdScubaDiving,
} from "react-icons/md";
import { RiCalendarScheduleLine } from "react-icons/ri";
import { TbAirConditioning } from "react-icons/tb";
import { WiHumidity } from "react-icons/wi";

export default function NavLinks({ isCollapse }) {
	const { emp_data } = usePage().props;
	return (
		<nav
			className="flex flex-col space-y-1 overflow-y-auto"
			style={{ scrollbarWidth: "none" }}
		>
			<SidebarLink
				href={route("perform-checklist.index")}
				label="Perform a checklist"
				icon={<FaPlay className="w-full h-full" />}
				isIconOnly={isCollapse}
				linkButtonClassName={"bg-primary btn text-white hover:bg-secondary"}
			/>

			<SidebarLink
				href={route("dashboard")}
				label="Dashboard"
				icon={<LuLayoutDashboard className="w-full h-full" />}
				notifications={5}
				isIconOnly={isCollapse}
			/>

			<SidebarLink
				href={route("checklist-instance.index")}
				label="List of performed checklist"
				icon={<BiTask className="w-full h-full" />}
				isIconOnly={isCollapse}
			/>

			{/* <Dropdown
                label="Checklist Items"
                icon={<LuLayoutDashboard className="w-full h-full" />}
                links={[
                    {
                        href: route("admin"),
                        label: "Profile",
                        icon: <LuLayoutDashboard className="w-full h-full" />,
                    },
                    {
                        href: route("admin"),
                        label: "Account",
                        notification: 125,
                        icon: <LuLayoutDashboard className="w-full h-full" />,
                    },
                    {
                        href: route("dashboard"),
                        label: "No notifications",
                        icon: <LuLayoutDashboard className="w-full h-full" />,
                    },
                ]}
                isIconOnly={isCollapse}
                // notification={true}
            /> */}

			<SidebarLink
				href={route("utility-trash")}
				label="Utility Trash"
				// icon={<LuLayoutDashboard className="w-full h-full" />}
				icon={<FaTrash className="w-full h-full" />}
				notifications={5}
				isIconOnly={isCollapse}
			/>
			{/* <SidebarLink
                href={route("utility-trash")}
                label="Fire Extinguisher"
                icon={<FaFireExtinguisher className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            />
            <SidebarLink
                href={route("utility-trash")}
                label="Fire Pump"
                icon={<FaGasPump className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            />
            <SidebarLink
                href={route("utility-trash")}
                label="Fire Hydrant"
                icon={<MdFireHydrantAlt className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            />
            <SidebarLink
                href={route("utility-trash")}
                label="Air Receiver Tank"
                icon={<MdPropaneTank className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            />
            <SidebarLink
                href={route("utility-trash")}
                label="Air Conditioning"
                icon={<TbAirConditioning className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            />
            <SidebarLink
                href={route("utility-trash")}
                label="Air Compressor"
                icon={<MdAir className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            />
            <SidebarLink
                href={route("utility-trash")}
                label="Water Tank"
                icon={<GiWaterTank className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            />
            <SidebarLink
                href={route("utility-trash")}
                label="Humidifier water filter"
                icon={<WiHumidity className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            />
            <SidebarLink
                href={route("utility-trash")}
                label="Humidifier water filter"
                icon={<WiHumidity className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            />
            <SidebarLink
                href={route("utility-trash")}
                label="Water Pump"
                icon={<IoWaterSharp className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            /> */}
			<SidebarLink
				href={route("hazardous-log-sheet.index")}
				label="Hazardous Waste Turn-over"
				icon={<FaBiohazard className="w-full h-full" />}
				notifications={5}
				isIconOnly={isCollapse}
			/>

			<Dropdown
				label="Settings"
				icon={<IoSettingsOutline className="w-full h-full" />}
				links={[
					{
						href: route("locations.index"),
						label: "Locations",
						icon: <FaLocationDot className="w-full h-full" />,
					},
					{
						href: route("checklist-items.index"),
						label: "Checklists",
						icon: <MdChecklist className="w-full h-full" />,
					},
					{
						href: route("checklist-assets.index"),
						label: "Checklists' Assets",
						icon: <FaListAlt className="w-full h-full" />,
					},
					{
						href: route("check-items.index"),
						label: "Check Items",
						icon: <FaCubes className="w-full h-full" />,
					},
					{
						href: route("assets.index"),
						label: "List of Assets",
						icon: <FaCubes className="w-full h-full" />,
					},
					{
						href: route("asset-pm-schedule.index"),
						label: "Assets PM Schedule",
						icon: <FaTools className="w-full h-full" />,
					},
				]}
				isIconOnly={isCollapse}
				// notification={true}
			/>

			{/* TODO: logsheet in a dropdown? or just like in chcecklist flow? */}

			{/* TODO: use checklist table instead of separate table??? */}
			<Dropdown
				label="Chemicals"
				icon={<FaBiohazard className="w-full h-full" />}
				links={[
					{
						href: route("chemicals.index"),
						label: "Chemical Inventory",
						icon: <GiChemicalDrop className="w-full h-full" />,
					},
					{
						href: route("chemicals-sds.index"),
						label: "Chemical SDS Monitoring",
						icon: <MdHealthAndSafety className="w-full h-full" />,
					},
				]}
				isIconOnly={isCollapse}
				// notification={true}
			/>
			{/* <SidebarLink
                href={route("dashboard")}
                label="Dashboard"
                icon={<LuLayoutDashboard className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            />

            <SidebarLink
                href={route("dashboard")}
                label="Dashboard"
                icon={<LuLayoutDashboard className="w-full h-full" />}
                notifications={5}
                isIconOnly={isCollapse}
            /> */}
			{["superadmin", "admin"].includes(emp_data?.emp_system_role) && (
				<div>
					<SidebarLink
						href={route("admin")}
						label="Administrators"
						icon={<LuLayoutDashboard className="w-full h-full" />}
						isIconOnly={isCollapse}
						// notifications={5}
					/>
				</div>
			)}
		</nav>
	);
}
