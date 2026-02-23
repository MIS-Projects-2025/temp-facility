import Dropdown from "@/Components/sidebar/Dropdown";
import SidebarLink from "@/Components/sidebar/SidebarLink";
import { usePage } from "@inertiajs/react";
import { BiTask } from "react-icons/bi";
import { FaCheckCircle, FaListAlt, FaTools, FaTrash } from "react-icons/fa";
import { FaBiohazard, FaCubes, FaLocationDot, FaPlay } from "react-icons/fa6";
import { GiChemicalDrop } from "react-icons/gi";
import { IoSettingsOutline } from "react-icons/io5";
import { LuLayoutDashboard } from "react-icons/lu";
import { MdChecklist, MdHealthAndSafety } from "react-icons/md";

export default function NavLinks({ isCollapse }) {
	const { emp_data } = usePage().props;
	return (
		<nav
			className="flex flex-col space-y-1 overflow-y-auto"
			style={{ scrollbarWidth: "none" }}
		>
			<SidebarLink
				href={route("perform.checklist.index")}
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

			{/* <SidebarLink
				href={route("utility-trash")}
				label="View Done Checklist"
				// icon={<LuLayoutDashboard className="w-full h-full" />}

				icon={<FaTrash className="w-full h-full" />}
				notifications={5}
				isIconOnly={isCollapse}
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
						href: route("checklist.index"),
						label: "Checklists",
						icon: <FaCheckCircle className="w-full h-full" />,
					},
					{
						href: route("checklist-items.index"),
						label: "Checklists' Items",
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
						href: route("perform.sds-monitoring.index"),
						label: "Perform SDS Monitoring",
						icon: <FaPlay className="w-full h-full" />,
					},
					{
						href: route("chemicals.index"),
						label: "Chemical Inventory",
						icon: <GiChemicalDrop className="w-full h-full" />,
					},
					{
						href: route("chemicals-sds-instances.index"),
						label: "Performed Chemical SDS Monitoring List",
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
