import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { CustomProgress } from "@/components/ui/custom-progress";
import { DailyStatusSummary } from "@shared/schema";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { ExportButton } from "@/components/ui/export-button";
import { formatDate } from "@/lib/exportUtils";

interface StatusCategory {
  label: string;
  value: number;
  percentage: number;
  color: string;
}

interface StatusSummaryCardProps {
  branchName: string;
  date: string;
  summary: DailyStatusSummary;
  statusEntries?: any[];
}

export function StatusSummaryCard({
  branchName,
  date,
  summary,
  statusEntries = [],
}: StatusSummaryCardProps) {
  const [selectedCategory, setSelectedCategory] = useState<StatusCategory | null>(null);
  const [isDialogOpen, setIsDialogOpen] = useState(false);

  const formattedDate = formatDate(date);
  
  // Calculate total active personnel (excluding deserted)
  const activeTotal = summary.total - summary.deserted;
  
  // Calculate percentages and prepare data
  const categories: StatusCategory[] = [
    {
      label: "Present",
      value: summary.present,
      percentage: activeTotal > 0 ? (summary.present / activeTotal) * 100 : 0,
      color: "bg-green-500",
    },
    {
      label: "Sick",
      value: summary.sick,
      percentage: activeTotal > 0 ? (summary.sick / activeTotal) * 100 : 0,
      color: "bg-amber-500",
    },
    {
      label: "AWOL",
      value: summary.awol,
      percentage: activeTotal > 0 ? (summary.awol / activeTotal) * 100 : 0,
      color: "bg-red-500",
    },
    {
      label: "On Leave",
      value: summary.onLeave,
      percentage: activeTotal > 0 ? (summary.onLeave / activeTotal) * 100 : 0,
      color: "bg-blue-400",
    },
    {
      label: "On Course",
      value: summary.onCourse,
      percentage: activeTotal > 0 ? (summary.onCourse / activeTotal) * 100 : 0,
      color: "bg-indigo-500",
    },
    {
      label: "On Suspension",
      value: summary.onSuspension,
      percentage: activeTotal > 0 ? (summary.onSuspension / activeTotal) * 100 : 0,
      color: "bg-slate-500",
    },
    {
      label: "Deserted",
      value: summary.deserted,
      percentage: summary.total > 0 ? (summary.deserted / summary.total) * 100 : 0,
      color: "bg-red-700",
    },
  ];

  // Filter status entries based on selected category
  const getFilteredEntries = () => {
    if (!selectedCategory || !statusEntries.length) return [];

    const categoryStatusMap: Record<string, string[]> = {
      "Present": ["present"],
      "Sick": ["sick"],
      "AWOL": ["awol"],
      "On Leave": ["leave_pass", "leave_maternity", "leave_paternity", "leave_study"],
      "On Course": ["on_course"],
      "On Suspension": ["on_suspension"],
      "Deserted": ["deserted"],
    };

    const statusesToFilter = categoryStatusMap[selectedCategory.label] || [];
    return statusEntries.filter(entry => statusesToFilter.includes(entry.status));
  };

  const filteredEntries = getFilteredEntries();

  // Export headers for the dialog entries
  const exportHeaders = [
    { key: "employee.fileNumber", label: "File Number" },
    { key: "employee.fullName", label: "Full Name" },
    { key: "employee.rank", label: "Rank" },
    { key: "employee.gender", label: "Gender" },
    { key: "employee.role", label: "Role" },
    { key: "status", label: "Status" },
    { key: "remarks", label: "Remarks" },
  ];

  // Handle click on a status category
  const handleCategoryClick = (category: StatusCategory) => {
    setSelectedCategory(category);
    setIsDialogOpen(true);
  };

  return (
    <>
      <Card>
        <CardHeader className="pb-2">
          <div className="flex items-center justify-between">
            <CardTitle className="text-lg font-bold">Status Summary</CardTitle>
            <div className="text-sm text-muted-foreground">{formattedDate}</div>
          </div>
          <div className="text-sm font-medium">{branchName}</div>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {categories.map((category) => (
              <div
                key={category.label}
                className="grid grid-cols-[1fr_80px] gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded-md transition-colors"
                onClick={() => handleCategoryClick(category)}
              >
                <div className="space-y-1">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">{category.label}</span>
                    <span className="text-sm text-muted-foreground">
                      {category.value} ({category.percentage.toFixed(1)}%)
                    </span>
                  </div>
                  <CustomProgress
                    value={category.percentage}
                    color={category.color}
                    className="h-2"
                  />
                </div>
              </div>
            ))}
          </div>

          <div className="grid grid-cols-2 gap-4 mt-6">
            <div className="text-center p-2 bg-navy-50 rounded-md">
              <div className="text-sm text-muted-foreground">Male</div>
              <div className="text-xl font-bold">{summary.maleCount}</div>
            </div>
            <div className="text-center p-2 bg-navy-50 rounded-md">
              <div className="text-sm text-muted-foreground">Female</div>
              <div className="text-xl font-bold">{summary.femaleCount}</div>
            </div>
          </div>
        </CardContent>
      </Card>

      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>
              {selectedCategory?.label} Personnel - {branchName}
            </DialogTitle>
            <DialogDescription>
              Showing {filteredEntries.length} personnel with status{' '}
              <Badge variant="outline">{selectedCategory?.label}</Badge> on {formattedDate}
            </DialogDescription>
          </DialogHeader>

          {filteredEntries.length > 0 ? (
            <div>
              <div className="flex justify-end mb-4">
                <ExportButton
                  data={filteredEntries}
                  headers={exportHeaders}
                  filename={`${selectedCategory?.label.toLowerCase().replace(' ', '_')}_personnel_${date}`}
                  title={`${selectedCategory?.label} Personnel Report`}
                  subtitle={branchName}
                  date={formattedDate}
                  additionalInfo={`Total: ${filteredEntries.length} personnel`}
                />
              </div>

              <div className="border rounded-md">
                <table className="w-full text-sm">
                  <thead className="bg-slate-50">
                    <tr>
                      <th className="px-4 py-2 text-left">File No.</th>
                      <th className="px-4 py-2 text-left">Name</th>
                      <th className="px-4 py-2 text-left">Rank</th>
                      <th className="px-4 py-2 text-left">Role</th>
                      <th className="px-4 py-2 text-left">Remarks</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {filteredEntries.map((entry) => (
                      <tr key={entry.id}>
                        <td className="px-4 py-2">{entry.employee.fileNumber}</td>
                        <td className="px-4 py-2">{entry.employee.fullName}</td>
                        <td className="px-4 py-2">{entry.employee.rank}</td>
                        <td className="px-4 py-2">{entry.employee.role}</td>
                        <td className="px-4 py-2">{entry.remarks || "-"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="text-center py-8 text-muted-foreground">
              No personnel with this status.
            </div>
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}