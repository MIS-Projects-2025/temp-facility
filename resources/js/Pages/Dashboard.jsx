import MultiSelectSearchableDropdown from "@/Components/MultiSelectSearchableDropdown";
import { useLocationStore } from "@/Store/locationStore";
import { Head, usePage } from "@inertiajs/react";

export default function Dashboard() {
    const props = usePage().props;

    return (
        <>
            <Head title="Dashboard" />

            <h1 className="text-2xl font-bold">Dashboard</h1>
            {/* <MultiSelectSearchableDropdown
                options={
                    locations?.map((opt) => ({
                        value: opt.location_name,
                        label: null,
                    })) || []
                }
                // onChange={handlePackageNamesChange}
                // defaultSelectedOptions={selectedPackageNames}
                // isLoading={isPackagesLoading}
                itemName="Package List"
                prompt="Select packages"
                contentClassName="w-200 h-70"
                disableSelectedContainer
                singleSelect
                disableSearch
            /> */}
        </>
    );
}
