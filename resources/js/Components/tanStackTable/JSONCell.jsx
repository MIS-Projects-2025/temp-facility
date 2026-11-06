import { useEffect, useState } from "react";
import { FaArrowRight, FaKey, FaPlus, FaTrash } from "react-icons/fa";

const JSONcell = ({ row, getValue, table, column }) => {
	const value = getValue();
	const serialized = JSON.stringify(value ?? {});
	const [properties, setProperties] = useState(() =>
		Object.entries(value || {}),
	);

	// re-sync when the table value changes (e.g. after "Reset")
	useEffect(() => {
		setProperties(Object.entries(JSON.parse(serialized)));
	}, [serialized]);

	const commit = (entries) => {
		table.options.meta?.updateData(
			row.index,
			column?.columnDef.accessorKey,
			Object.fromEntries(entries),
		);
	};

	const updateProperty = (index, type, newVal) => {
		setProperties((prev) =>
			prev.map((entry, i) => {
				if (i !== index) return entry;
				return type === "key" ? [newVal, entry[1]] : [entry[0], newVal];
			}),
		);
	};

	const deleteProperty = (index) => {
		const next = properties.filter((_, i) => i !== index);
		setProperties(next);
		commit(next);
	};

	const addProperty = () => {
		setProperties((prev) => [...prev, ["", ""]]);
	};

	return (
		<div className="flex w-full flex-col gap-2 border border-base-content/20">
			{properties.map(([key, val], index) => (
				<div key={index} className="flex items-center gap-2 group">
					<label className="input bg-base-100/20 flex-1">
						<FaKey className="opacity-25" />
						<input
							type="text"
							value={key}
							placeholder="Key"
							onChange={(e) => updateProperty(index, "key", e.target.value)}
							onBlur={() => commit(properties)}
						/>
					</label>

					<label className="input bg-base-100/20 flex-1">
						<FaArrowRight className="opacity-25" />
						<input
							type="text"
							value={val}
							placeholder="Value"
							onChange={(e) => updateProperty(index, "value", e.target.value)}
							onBlur={() => commit(properties)}
						/>
					</label>

					<button
						type="button"
						className="btn opacity-0 group-hover:opacity-100 btn-square"
						onClick={() => deleteProperty(index)}
					>
						<FaTrash className="opacity-25" />
					</button>
				</div>
			))}

			<button type="button" className="btn btn-sm" onClick={addProperty}>
				<FaPlus className="mr-1" /> Add Property
			</button>
		</div>
	);
};

export default JSONcell;