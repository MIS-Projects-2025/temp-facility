import CheckBoxColumn from "@/Components/tanStackTable/CheckBoxColumn";
import DefaultEditableColumn from "@/Components/tanStackTable/DefaultEditableColumn";
import updateNested from "@/Utils/updateNested";
import { getCoreRowModel, useReactTable } from "@tanstack/react-table";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

/**
 * useEditableTable - reusable hook for editable React Table
 *
 * @param {Array} initialData - initial table data
 * @param {Array} columns - column definitions
 * @param {Object} options - optional settings
 *        options.defaultColumn - default column definition
 *        options.isMultipleSelection - enable multiple selection
 *        options.createEmptyRow - callback to create an empty row
 *        options.columnVisibility - list of visible columns
 *        options.setColumnVisibility - callback to update column visibility
 * @returns {Object} table instance + state helpers
 */
export function useEditableTable(initialData = [], columns, options = {}) {
	const {
		defaultColumn = DefaultEditableColumn,
		isMultipleSelection = false,
		createEmptyRow,
		columnVisibility,
		setColumnVisibility,
	} = options;

	const [data, setData] = useState(initialData);
	const [editedRows, setEditedRows] = useState({});
	const [originalData, setOriginalData] = useState({});
	const [changes, setChanges] = useState([]);
	const wasEmptyRef = useRef(false);

	useEffect(() => {
		const rows = initialData;
		const isEmpty = rows.length === 0;

		// callers often pass `something || []` which is a brand new array on every render;
		// without this guard an empty table would reset itself forever
		if (isEmpty && wasEmptyRef.current) return;
		wasEmptyRef.current = isEmpty;

		setData(rows);

		const map = {};
		rows.forEach((row) => {
			map[row.id] = row;
		});
		setOriginalData(map);
		setEditedRows({});
	}, [initialData]);

	const updateData = useCallback((rowIndex, accessorKey, value) => {
		setData((prevData) => {
			const row = prevData[rowIndex];
			const updatedRow = updateNested(row, accessorKey, value);

			if (JSON.stringify(row) === JSON.stringify(updatedRow)) return prevData;

			const newData = [...prevData];
			newData[rowIndex] = updatedRow;

			const rowId = row.id;
			setEditedRows((prev) => ({
				...prev,
				[rowId]: updatedRow,
			}));

			return newData;
		});
	}, []);

	const derivedColumns = useMemo(() => {
		const visibleColumns = columns.filter((col) => !col.meta?.hidden);

		if (!isMultipleSelection) return visibleColumns;

		return [CheckBoxColumn, ...visibleColumns];
	}, [columns, isMultipleSelection]);

	const table = useReactTable({
		data,
		columns: derivedColumns,
		defaultColumn,
		getRowId: (row) => row.id.toString(),
		getCoreRowModel: getCoreRowModel(),
		columnResizeDirection: "ltr",
		columnResizeMode: "onChange",
		meta: {
			updateData,
		},
		...(columnVisibility &&
			setColumnVisibility && {
				state: { columnVisibility },
				onColumnVisibilityChange: setColumnVisibility,
			}),
	});

	// drop stale selection whenever the server data changes (after delete, page change, search)
	useEffect(() => {
		if (Object.keys(table.getState().rowSelection).length > 0) {
			table.resetRowSelection(true);
		}
	}, [initialData, table]);

	const handleAddNewRow = useCallback(
		(overrides = {}) => {
			const newId = `new-${table.getRowCount() + 1}`;

			const baseRow =
				typeof createEmptyRow === "function" ? createEmptyRow() : {};

			const newRow = {
				id: newId,
				...baseRow,
				...overrides,
				isNew: true,
			};

			setData((prev) => [newRow, ...prev]);

			setEditedRows((prev) => ({
				[newId]: newRow,
				...prev,
			}));
		},
		[table, createEmptyRow],
	);

	const handleResetChanges = () => {
		if (Object.keys(editedRows).length === 0) {
			alert("No changes to reset.");
			return;
		}

		if (!confirm("Are you sure you want to discard all changes?")) return;

		setEditedRows({});
		setChanges([]);
		setData(initialData); // keeps the server's row order
	};

	const getChanges = () => {
		const result = [];
		const norm = (v) => (v === undefined || v === "" ? null : v);

		for (const rowId in editedRows) {
			const original = originalData[rowId] || {};
			const edited = editedRows[rowId];

			for (const field in edited) {
				const before = original[field];
				const after = edited[field];

				if (JSON.stringify(norm(before)) !== JSON.stringify(norm(after))) {
					result.push({
						rowId,
						field,
						before: before ?? null,
						after,
					});
				}
			}
		}

		setChanges(result);
		return result;
	};

	return {
		table,
		data,
		setData,
		editedRows,
		setEditedRows,
		handleAddNewRow,
		handleResetChanges,
		changes,
		getChanges,
		checkedRows: Object.keys(table.getState().rowSelection),
		updateData,
	};
}