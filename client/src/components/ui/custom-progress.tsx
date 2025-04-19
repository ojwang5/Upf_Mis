import { cn } from "@/lib/utils";

interface CustomProgressProps {
  value: number;
  color?: string;
  className?: string;
}

export function CustomProgress({
  value,
  color = "bg-primary",
  className,
}: CustomProgressProps) {
  return (
    <div className={cn("h-2 w-full bg-slate-100 rounded-full overflow-hidden", className)}>
      <div
        className={cn("h-full transition-all", color)}
        style={{ width: `${Math.min(Math.max(value, 0), 100)}%` }}
      />
    </div>
  );
}