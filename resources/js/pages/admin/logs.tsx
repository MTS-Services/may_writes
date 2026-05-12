export default function AdminLogsPage({ lines }: { lines: string[] }) {
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-semibold">Logs</h1>
      <div className="max-h-[70vh] overflow-auto rounded-lg border bg-black p-4 font-mono text-xs text-neutral-200">
        {lines.map((line, index) => {
          let className = 'text-neutral-200';
          if (line.includes('ERROR')) className = 'text-red-400';
          if (line.includes('WARNING')) className = 'text-amber-400';
          if (line.includes('INFO')) className = 'text-emerald-400';

          return (
            <div key={`${index}-${line.slice(0, 20)}`} className={className}>
              {line}
            </div>
          );
        })}
      </div>
    </div>
  );
}
