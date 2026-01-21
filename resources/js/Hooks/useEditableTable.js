import { useState, useCallback } from "react";
import { useReactTable, getCoreRowModel } from "@tanstack/react-table";
import DefaultEditableColumn from "@/Components/tanStackTable/defaultEditableColumn";
import updateNested from "@/Utils/updateNested";
/**
 * useEditableTable - reusable hook for editable React Table
 *
 * @param {Array} initialData - initial table data
 * @param {Array} columns - column definitions
 * @param {Object} options - optional settings
 *        options.defaultColumn - default column definition
 * @returns {Object} table instance + state helpers
 */
export function useEditableTable(initialData = [], columns, options = {}) {
    const { defaultColumn = DefaultEditableColumn, onEdit } = options;
    const [data, setData] = useState(initialData);
    const [editedRows, setEditedRows] = useState({});

    console.log("🚀 ~ useEditableTable ~ data:", data)

    const updateData = useCallback(
        (rowIndex, accessorKey, value) => {
            setData(prevData => {
                const row = prevData[rowIndex];
                const updatedRow = updateNested(row, accessorKey, value);
                
                if (JSON.stringify(row) === JSON.stringify(updatedRow)) return prevData;
                
                const newData = [...prevData];
                newData[rowIndex] = updatedRow;

                const rowId = row.id;
                setEditedRows(prev => {
                    const editedRow = prev[rowId] || {};
                    return {
                    ...prev,
                    [rowId]: updatedRow,
                    };
                });

                return newData;
            });
        },
        [onEdit]
    );

    const table = useReactTable({
        data,
        columns: columns.filter(col => !col.meta?.hidden),
        defaultColumn,
        getRowId: (row) => row.id.toString(),
        getCoreRowModel: getCoreRowModel(),
        columnResizeDirection: "ltr",
        columnResizeMode: "onChange",
        meta: {
            updateData,
        },
    });

    return {
        table,
        data,
        setData,
        editedRows,
        setEditedRows,
        updateData,
    };
}
