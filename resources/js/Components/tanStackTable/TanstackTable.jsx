import React from "react";
import { TableHeader } from "./TableHeader";
import TableBody from "./TableBody";

const TanstackTable = ({ table, isTableLoading }) => {
    const tableContainerRef = React.useRef(null);

    return (
        <div
            className="container border border-base-300 my-2"
            ref={tableContainerRef}
            style={{
                overflow: "auto",
                position: "relative",
                height: "680px",
            }}
        >
            <table
                className="border border-base-300 w-fit"
                style={{ width: table.getCenterTotalSize() }}
            >
                <TableHeader table={table} />
                <TableBody
                    table={table}
                    tableContainerRef={tableContainerRef}
                    isTableLoading={isTableLoading}
                />
            </table>
        </div>
    );
};

export default TanstackTable;
