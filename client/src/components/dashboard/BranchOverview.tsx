import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { formatDate } from "@/lib/exportUtils";
import { StatusSummaryCard } from "./StatusSummaryCard";
import { Button } from "@/components/ui/button";
import { ExportButton } from "@/components/ui/export-button";
import { useEffect, useState } from "react";
import { Loader2, RefreshCw } from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import { apiRequest } from "@/lib/queryClient";

export default function BranchOverview() {
  const { user } = useAuth();
  const [refreshInterval, setRefreshInterval] = useState<number | null>(30000); // 30 seconds refresh by default
  const [lastUpdated, setLastUpdated] = useState<Date>(new Date());
  
  // Get branch summaries
  const { data: summaries = [], isLoading, refetch, isFetching } = useQuery<any[]>({
    queryKey: ['/api/protected/summary'],
    refetchInterval: refreshInterval || undefined,
  });
  
  // Get detailed entries (for export and filtering)
  const { data: detailedData = [] } = useQuery<any[]>({
    queryKey: ['/api/protected/daily-status-entries'],
    refetchInterval: refreshInterval || undefined,
  });
  
  // When data refreshes, update the lastUpdated timestamp
  useEffect(() => {
    if (!isFetching) {
      setLastUpdated(new Date());
    }
  }, [isFetching]);
  
  // Toggle auto-refresh
  const toggleRefresh = () => {
    setRefreshInterval(prev => prev ? null : 30000);
  };
  
  // Handle manual refresh
  const handleRefresh = () => {
    refetch();
  };
  
  // Create export data from summaries
  const createExportData = (summaries: any[] = []) => {
    return summaries.map(summary => ({
      branchName: summary.branch.name,
      branchLocation: summary.branch.location,
      date: summary.report?.date ? formatDate(summary.report.date) : 'No report',
      total: summary.summary.total,
      present: summary.summary.present,
      sick: summary.summary.sick,
      awol: summary.summary.awol,
      deserted: summary.summary.deserted,
      onLeave: summary.summary.onLeave,
      onCourse: summary.summary.onCourse,
      onSuspension: summary.summary.onSuspension,
      maleCount: summary.summary.maleCount,
      femaleCount: summary.summary.femaleCount,
    }));
  };
  
  // Export headers
  const exportHeaders = [
    { key: 'branchName', label: 'Branch' },
    { key: 'branchLocation', label: 'Location' },
    { key: 'date', label: 'Date' },
    { key: 'total', label: 'Total Personnel' },
    { key: 'present', label: 'Present' },
    { key: 'sick', label: 'Sick' },
    { key: 'awol', label: 'AWOL' },
    { key: 'deserted', label: 'Deserted' },
    { key: 'onLeave', label: 'On Leave' },
    { key: 'onCourse', label: 'On Course' },
    { key: 'onSuspension', label: 'On Suspension' },
    { key: 'maleCount', label: 'Male' },
    { key: 'femaleCount', label: 'Female' },
  ];
  
  // Function to get entries for a specific branch
  const getEntriesForBranch = (branchId: number) => {
    if (!detailedData) return [];
    return detailedData.filter((entry: any) => entry.report.branchId === branchId);
  };
  
  // Filter summaries based on user role and branch access
  const filteredSummaries = summaries?.filter((summary: any) => {
    if (user?.role === 'admin') return true;
    if (user?.branchAccess && summary.branch.code === user.branchAccess) return true;
    return false;
  });
  
  // Only allow the branch manager to see their branch's data
  const exportData = user?.role === 'admin' 
    ? createExportData(summaries) 
    : createExportData(filteredSummaries);
  
  if (isLoading) {
    return <LoadingSkeleton />;
  }
  
  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Branch Overview</h2>
          <p className="text-muted-foreground">
            Status summary across all branches
          </p>
        </div>
        
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={toggleRefresh}
            className={refreshInterval ? 'text-green-600' : 'text-slate-600'}
          >
            <RefreshCw className="h-4 w-4 mr-2" />
            {refreshInterval ? 'Auto-refresh On' : 'Auto-refresh Off'}
          </Button>
          
          <Button
            variant="outline"
            size="sm"
            onClick={handleRefresh}
            disabled={isFetching}
          >
            {isFetching ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <RefreshCw className="h-4 w-4" />
            )}
          </Button>
          
          <ExportButton
            data={exportData}
            headers={exportHeaders}
            filename="branch_status_summary"
            title="Branch Status Summary"
            subtitle={`Uganda Police Force - MDD Management System`}
            date={new Date().toDateString()}
            additionalInfo={`Last updated: ${lastUpdated.toLocaleTimeString()}`}
          />
        </div>
      </div>
      
      <div className="text-sm text-muted-foreground">
        Last updated: {lastUpdated.toLocaleTimeString()}
      </div>
      
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredSummaries?.map((summary: any) => (
          <StatusSummaryCard
            key={summary.branch.id}
            branchName={summary.branch.name}
            date={summary.report?.date || new Date().toISOString()}
            summary={summary.summary}
            statusEntries={getEntriesForBranch(summary.branch.id)}
          />
        ))}
      </div>
    </div>
  );
}

function LoadingSkeleton() {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Branch Overview</h2>
        <p className="text-muted-foreground">
          Status summary across all branches
        </p>
      </div>
      
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {[1, 2, 3].map((i) => (
          <Card key={i}>
            <CardHeader className="space-y-1 pb-2">
              <Skeleton className="h-5 w-1/2" />
              <Skeleton className="h-4 w-1/3" />
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {[1, 2, 3, 4, 5].map((j) => (
                  <div key={j} className="space-y-1">
                    <div className="flex justify-between">
                      <Skeleton className="h-4 w-24" />
                      <Skeleton className="h-4 w-16" />
                    </div>
                    <Skeleton className="h-2 w-full" />
                  </div>
                ))}
              </div>
              
              <div className="grid grid-cols-2 gap-4 mt-6">
                <div className="text-center p-2 rounded-md">
                  <Skeleton className="h-4 w-full mx-auto" />
                  <Skeleton className="h-6 w-8 mx-auto mt-1" />
                </div>
                <div className="text-center p-2 rounded-md">
                  <Skeleton className="h-4 w-full mx-auto" />
                  <Skeleton className="h-6 w-8 mx-auto mt-1" />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}