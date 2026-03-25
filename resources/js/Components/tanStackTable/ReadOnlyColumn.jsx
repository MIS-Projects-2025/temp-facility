const ReadOnlyColumns = ({
	accessorKey,
	header,
	options = {},
	formatter,
	cell,
}) => ({
	accessorKey,
	header,
	...options,
	cell:
		cell ??
		(({ getValue }) => {
			const value = getValue();
			const displayValue = formatter ? formatter(value) : (value ?? "-");
			return (
				<span className="opacity-60 flex items-center cursor-not-allowed">
					{displayValue}
				</span>
			);
		}),
});

export default ReadOnlyColumns;
