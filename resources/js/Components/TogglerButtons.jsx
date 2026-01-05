import React, { memo } from "react";
import { FaCheck } from "react-icons/fa6";
import { FaTimes } from "react-icons/fa";
import clsx from "clsx";

const TogglerButtons = memo(function TogglerButton({
    id,
    toggleButtons,
    visibleBars,
    toggleBar,
    toggleAll = null,
    buttonClassName = "",
}) {
    return (
        <div className="join rounded-lg font-medium">
            {toggleButtons.map(({ key, label, activeClass, inactiveClass }) => (
                <button
                    key={key}
                    onClick={() => toggleBar(id, key)}
                    className={clsx(
                        "join-item flex btn btn-sm text-sm items-center gap-x-2 px-3 py-1 transition-colors duration-200",
                        visibleBars[key] ? activeClass : inactiveClass,
                        buttonClassName
                    )}
                >
                    {label}
                    {visibleBars[key] ? <FaCheck /> : <FaTimes />}
                </button>
            ))}

            {toggleAll && (
                <button
                    onClick={toggleAll}
                    className="join-item px-3 py-1 btn btn-sm text-sm btn-outline"
                >
                    All
                </button>
            )}
        </div>
    );
});

export default TogglerButtons;
