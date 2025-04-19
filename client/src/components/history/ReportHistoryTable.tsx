import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { formatDate } from "@/lib/exportUtils";
import { Button } from "@/components/ui/button";
import { Eye, FileText } from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { ExportButton } from "@/components/ui/export-button";
import { useAuth } from "@/hooks/use-auth";

// Type for the filter props
interface ReportFilterProps {
  branchId?: number | null;
  startDate?: string;
  endDate?: string;
}

export default function ReportHistoryTable({ 
  branchId = null, 
  startDate,
  endDate,
}: ReportFilterProps) {
  const { user } = useAuth();
  const [selectedReport, setSelectedReport] = useState<any | null>(null);
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  
  // Fetch reports
  const { data: reports = [], isLoading } = useQuery<any[]>({
    queryKey: ['/api/protected/daily-status'],
  });
  
  // Filter reports based on branch and date range
  const filteredReports = reports.filter((report: any) => {
    // Branch filter
    if (branchId && report.branchId !== branchId) return false;
    
    // Branch access filter (for branch managers)
    if (user?.role === 'branch_manager' && user?.branchAccess) {
      const hasBranchAccess = report.branch?.code === user.branchAccess;
      if (!hasBranchAccess) return false;
    }
    
    // Date range filter
    if (startDate) {
      const reportDate = new Date(report.date);
      const start = new Date(startDate);
      if (reportDate < start) return false;
    }
    
    if (endDate) {
      const reportDate = new Date(report.date);
      const end = new Date(endDate);
      end.setHours(23, 59, 59, 999); // End of day
      if (reportDate > end) return false;
    }
    
    return true;
  });
  
  // Function to view report details
  const handleViewReport = async (reportId: number) => {
    try {
      // Fetch detailed report
      const response = await fetch(`/api/protected/daily-status/${reportId}`);
      if (!response.ok) throw new Error("Failed to fetch report details");
      
      const reportData = await response.json();
      setSelectedReport(reportData);
      setIsDialogOpen(true);
    } catch (error) {
      console.error("Error fetching report:", error);
    }
  };
  
  // Export headers for the table
  const exportHeaders = [
    { key: "date", label: "Date" },
    { key: "branch.name", label: "Branch" },
    { key: "submittedBy", label: "Submitted By" },
    { key: "specialReport", label: "Special Notes" }
  ];
  
  // Export headers for detailed report view
  const detailedExportHeaders = [
    { key: "employee.fileNumber", label: "File Number" },
    { key: "employee.fullName", label: "Name" },
    { key: "employee.rank", label: "Rank" },
    { key: "employee.role", label: "Role" },
    { key: "status", label: "Status" },
    { key: "remarks", label: "Remarks" }
  ];
  
  // Function to get status badge color
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'present': return "bg-green-100 text-green-800";
      case 'sick': return "bg-amber-100 text-amber-800";
      case 'awol': return "bg-red-100 text-red-800";
      case 'deserted': return "bg-red-200 text-red-900";
      case 'leave_pass':
      case 'leave_maternity':
      case 'leave_paternity':
      case 'leave_study': return "bg-blue-100 text-blue-800";
      case 'on_course': return "bg-indigo-100 text-indigo-800";
      case 'on_suspension': return "bg-slate-100 text-slate-800";
      default: return "bg-slate-100 text-slate-800";
    }
  };
  
  // Function to format status for display
  const formatStatus = (status: string) => {
    return status
      .replace(/_/g, ' ')
      .split(' ')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  };
  
  if (isLoading) {
    return <LoadingSkeleton />;
  }
  
  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <h3 className="text-lg font-semibold">Report History</h3>
        <ExportButton
          data={filteredReports}
          headers={exportHeaders}
          filename="report_history"
          title="Daily Status Report History"
          subtitle="Uganda Police Force - MDD Management System"
          date={new Date().toDateString()}
        />
      </div>
      
      {filteredReports.length > 0 ? (
        <div className="border rounded-md">
          <table className="w-full">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left text-sm font-medium text-slate-700">Date</th>
                <th className="px-4 py-3 text-left text-sm font-medium text-slate-700">Branch</th>
                <th className="px-4 py-3 text-left text-sm font-medium text-slate-700">Submitted By</th>
                <th className="px-4 py-3 text-left text-sm font-medium text-slate-700">Special Notes</th>
                <th className="px-4 py-3 text-right text-sm font-medium text-slate-700">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {filteredReports.map((report: any) => (
                <tr key={report.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 text-sm text-slate-700">
                    {formatDate(report.date)}
                  </td>
                  <td className="px-4 py-3 text-sm text-slate-700">
                    {report.branch?.name || '-'}
                  </td>
                  <td className="px-4 py-3 text-sm text-slate-700">
                    {report.submittedByUser?.fullName || report.submittedBy}
                  </td>
                  <td className="px-4 py-3 text-sm text-slate-700">
                    {report.specialReport || '-'}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleViewReport(report.id)}
                    >
                      <Eye className="h-4 w-4 mr-1" />
                      View
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="text-center py-8 border rounded-md bg-slate-50">
          <FileText className="h-8 w-8 text-slate-400 mx-auto mb-2" />
          <p className="text-slate-600">No reports found for the selected criteria.</p>
        </div>
      )}
      
      {/* Report Detail Dialog */}
      {selectedReport && (
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogContent className="max-w-4xl">
            <DialogHeader>
              <DialogTitle>
                Daily Status Report - {formatDate(selectedReport.date)}
              </DialogTitle>
              <DialogDescription>
                {selectedReport.branch?.name || 'Unknown Branch'}
              </DialogDescription>
            </DialogHeader>
            
            <div className="space-y-4">
              <div className="flex justify-between items-center">
                <div>
                  <p className="text-sm text-slate-600">
                    Submitted by: {selectedReport.submittedByUser?.fullName || selectedReport.submittedBy}
                  </p>
                  {selectedReport.specialReport && (
                    <div className="mt-2 p-2 bg-amber-50 border border-amber-200 rounded-md">
                      <p className="text-sm font-medium text-amber-800">Special Report:</p>
                      <p className="text-sm text-amber-700">{selectedReport.specialReport}</p>
                    </div>
                  )}
                </div>
                
                <ExportButton
                  data={selectedReport.entries || []}
                  headers={detailedExportHeaders}
                  filename={`daily_status_${selectedReport.date}`}
                  title={`Daily Status Report - ${formatDate(selectedReport.date)}`}
                  subtitle={selectedReport.branch?.name || 'Unknown Branch'}
                  additionalInfo={`Submitted by: ${selectedReport.submittedByUser?.fullName || selectedReport.submittedBy}`}
                />
              </div>
              
              <div className="grid grid-cols-4 gap-4 mt-4">
                <div className="bg-green-50 p-3 rounded-md text-center">
                  <span className="text-sm text-green-700">Present</span>
                  <p className="text-xl font-bold text-green-800">{selectedReport.summary?.present || 0}</p>
                </div>
                <div className="bg-amber-50 p-3 rounded-md text-center">
                  <span className="text-sm text-amber-700">Sick</span>
                  <p className="text-xl font-bold text-amber-800">{selectedReport.summary?.sick || 0}</p>
                </div>
                <div className="bg-red-50 p-3 rounded-md text-center">
                  <span className="text-sm text-red-700">AWOL</span>
                  <p className="text-xl font-bold text-red-800">{selectedReport.summary?.awol || 0}</p>
                </div>
                <div className="bg-blue-50 p-3 rounded-md text-center">
                  <span className="text-sm text-blue-700">On Leave</span>
                  <p className="text-xl font-bold text-blue-800">{selectedReport.summary?.onLeave || 0}</p>
                </div>
              </div>
              
              <div className="border rounded-md mt-6">
                <table className="w-full">
                  <thead className="bg-slate-50">
                    <tr>
                      <th className="px-4 py-3 text-left text-sm font-medium text-slate-700">File Number</th>
                      <th className="px-4 py-3 text-left text-sm font-medium text-slate-700">Name</th>
                      <th className="px-4 py-3 text-left text-sm font-medium text-slate-700">Rank</th>
                      <th className="px-4 py-3 text-left text-sm font-medium text-slate-700">Status</th>
                      <th className="px-4 py-3 text-left text-sm font-medium text-slate-700">Remarks</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {selectedReport.entries?.map((entry: any) => (
                      <tr key={entry.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3 text-sm text-slate-700">
                          {entry.employee?.fileNumber || '-'}
                        </td>
                        <td className="px-4 py-3 text-sm text-slate-700">
                          {entry.employee?.fullName || '-'}
                        </td>
                        <td className="px-4 py-3 text-sm text-slate-700">
                          {entry.employee?.rank || '-'}
                        </td>
                        <td className="px-4 py-3 text-sm">
                          <Badge className={getStatusColor(entry.status)}>
                            {formatStatus(entry.status)}
                          </Badge>
                        </td>
                        <td className="px-4 py-3 text-sm text-slate-700">
                          {entry.remarks || '-'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      )}
    </div>
  );
}

function LoadingSkeleton() {
  return (
    <div className="space-y-4">
      <div className="flex justify-between items-center mb-4">
        <Skeleton className="h-6 w-32" />
        <Skeleton className="h-9 w-24" />
      </div>
      
      <div className="border rounded-md">
        <div className="bg-slate-50 py-3 px-4">
          <div className="grid grid-cols-5 gap-4">
            <Skeleton className="h-5 w-20" />
            <Skeleton className="h-5 w-24" />
            <Skeleton className="h-5 w-28" />
            <Skeleton className="h-5 w-24" />
            <Skeleton className="h-5 w-16 ml-auto" />
          </div>
        </div>
        
        <div className="divide-y">
          {[1, 2, 3, 4, 5].map((i) => (
            <div key={i} className="p-4">
              <div className="grid grid-cols-5 gap-4">
                <Skeleton className="h-5 w-24" />
                <Skeleton className="h-5 w-28" />
                <Skeleton className="h-5 w-32" />
                <Skeleton className="h-5 w-32" />
                <div className="flex justify-end">
                  <Skeleton className="h-8 w-16" />
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}