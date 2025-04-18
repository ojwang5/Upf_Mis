import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { format } from 'date-fns';
import Layout from '@/components/layout/Layout';
import StatusSummaryCard from '@/components/daily-status/StatusSummaryCard';
import StatusTable from '@/components/daily-status/StatusTable';
import { useAuth } from '@/hooks/use-auth';
import { apiRequest, queryClient } from '@/lib/queryClient';
import { useToast } from '@/hooks/use-toast';
import { 
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
  CardFooter
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { 
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Loader2, Save, Download, Calendar } from 'lucide-react';

export default function DailyStatusPage() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [selectedBranchId, setSelectedBranchId] = useState<number>(0);
  const [selectedDate, setSelectedDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [specialReport, setSpecialReport] = useState<string>('');
  const [currentReportId, setCurrentReportId] = useState<number | null>(null);
  const [isCreatingReport, setIsCreatingReport] = useState<boolean>(false);
  
  // Fetch branches
  const { data: branches, isLoading: isLoadingBranches } = useQuery({ 
    queryKey: ['/api/branches'] 
  });

  // If the user is a branch manager, preselect their branch
  const { data: userData } = useQuery({ 
    queryKey: ['/api/user'],
    onSuccess: (data) => {
      if (data?.branchAccess && branches) {
        const userBranch = branches.find(branch => branch.code === data.branchAccess);
        if (userBranch) {
          setSelectedBranchId(userBranch.id);
        }
      }
    },
    enabled: !!branches
  });

  // Check if a report exists for the selected date and branch
  const { data: reportExists, isLoading: isCheckingReport, refetch: recheckReport } = useQuery({
    queryKey: [`/api/protected/daily-status/check`, selectedBranchId, selectedDate],
    queryFn: async () => {
      try {
        if (!selectedBranchId) return null;
        
        // Try to find an existing report
        const reports = await queryClient.fetchQuery({ 
          queryKey: ['/api/protected/daily-status']
        });
        
        const matchingReport = reports?.find(
          (r: any) => 
            r.branchId === selectedBranchId && 
            new Date(r.date).toISOString().split('T')[0] === selectedDate
        );
        
        if (matchingReport) {
          setCurrentReportId(matchingReport.id);
          setSpecialReport(matchingReport.specialReport || '');
          return matchingReport;
        }
        
        return null;
      } catch (error) {
        console.error("Error checking for report:", error);
        return null;
      }
    },
    enabled: !!selectedBranchId
  });

  // Create new daily status report
  const createReportMutation = useMutation({
    mutationFn: async () => {
      return await apiRequest(
        'POST', 
        '/api/protected/daily-status', 
        { 
          date: selectedDate,
          branchId: selectedBranchId,
          specialReport
        }
      );
    },
    onSuccess: async (response) => {
      const data = await response.json();
      setCurrentReportId(data.id);
      toast({
        title: "Report created",
        description: "Daily status report has been created successfully.",
      });
      recheckReport();
    },
    onError: (error) => {
      toast({
        title: "Failed to create report",
        description: error.message,
        variant: "destructive",
      });
    }
  });

  // Update report's special report field
  const updateSpecialReportMutation = useMutation({
    mutationFn: async () => {
      return await apiRequest(
        'PATCH', 
        `/api/protected/daily-status/${currentReportId}`, 
        { specialReport }
      );
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [`/api/protected/daily-status/${currentReportId}`] });
      toast({
        title: "Report saved",
        description: "The special report has been updated.",
      });
    },
    onError: (error) => {
      toast({
        title: "Failed to update report",
        description: error.message,
        variant: "destructive",
      });
    }
  });

  const handleSpecialReportChange = (value: string) => {
    setSpecialReport(value);
  };

  const handleCreateReport = () => {
    if (!selectedBranchId) {
      toast({
        title: "Branch required",
        description: "Please select a branch to create a report.",
        variant: "destructive",
      });
      return;
    }
    
    setIsCreatingReport(true);
  };

  const confirmCreateReport = () => {
    createReportMutation.mutate();
    setIsCreatingReport(false);
  };

  const handleSaveReport = () => {
    if (currentReportId) {
      updateSpecialReportMutation.mutate();
    } else if (selectedBranchId) {
      setIsCreatingReport(true);
    }
  };

  const handleDateChange = (date: string) => {
    setSelectedDate(date);
    setCurrentReportId(null);
    setSpecialReport('');
  };

  const handleBranchChange = (branchId: string) => {
    setSelectedBranchId(parseInt(branchId));
    setCurrentReportId(null);
    setSpecialReport('');
  };

  const isLoading = isLoadingBranches || isCheckingReport;
  const branchOptions = branches || [];
  const canEdit = true; // In a real app, this would be based on permissions
  const formattedDate = selectedDate ? format(new Date(selectedDate), 'MMMM d, yyyy') : '';

  // Determine if user can select a branch based on role
  const canSelectBranch = user?.role === 'admin';

  return (
    <Layout
      title="Daily Status"
      description="Track attendance and activities"
    >
      <div className="flex flex-col md:flex-row md:items-center justify-between mb-6">
        <div className="mt-4 md:mt-0 flex items-center space-x-3">
          <Button 
            className="bg-blue-600 hover:bg-blue-700 text-white" 
            onClick={handleSaveReport}
            disabled={!selectedBranchId || (!currentReportId && !createReportMutation.isPending)}
          >
            <Save className="mr-2 h-4 w-4" />
            Save Report
          </Button>
          <Button variant="outline">
            <Download className="mr-2 h-4 w-4" />
            Export
          </Button>
        </div>
      </div>
      
      {/* Filter Controls */}
      <Card className="mb-6">
        <CardContent className="py-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            {canSelectBranch ? (
              <div className="relative">
                <label htmlFor="branch-filter" className="block text-xs font-medium text-gray-600 mb-1">Branch</label>
                <Select 
                  value={selectedBranchId.toString()} 
                  onValueChange={handleBranchChange}
                  disabled={isLoading}
                >
                  <SelectTrigger id="branch-filter">
                    <SelectValue placeholder="Select branch" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">Select a branch</SelectItem>
                    {branchOptions.map(branch => (
                      <SelectItem key={branch.id} value={branch.id.toString()}>
                        {branch.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            ) : (
              <div className="relative">
                <label className="block text-xs font-medium text-gray-600 mb-1">Branch</label>
                <Input 
                  value={branchOptions.find(b => b.code === user?.branchAccess)?.name || 'Unknown Branch'} 
                  disabled 
                />
              </div>
            )}
            
            <div>
              <label htmlFor="date-filter" className="block text-xs font-medium text-gray-600 mb-1">Date</label>
              <Input 
                id="date-filter" 
                type="date" 
                value={selectedDate}
                onChange={(e) => handleDateChange(e.target.value)}
                className="w-full"
              />
            </div>
            
            <div className="relative">
              <label htmlFor="supervisor-filter" className="block text-xs font-medium text-gray-600 mb-1">Supervisor Type</label>
              <Select defaultValue="all">
                <SelectTrigger id="supervisor-filter">
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Types</SelectItem>
                  <SelectItem value="officer">Officers</SelectItem>
                  <SelectItem value="nco">NCOs</SelectItem>
                  <SelectItem value="constable">Constables</SelectItem>
                </SelectContent>
              </Select>
            </div>
            
            <div>
              <label htmlFor="search-filter" className="block text-xs font-medium text-gray-600 mb-1">Search</label>
              <div className="relative">
                <Input 
                  id="search-filter" 
                  placeholder="Search by name or ID..." 
                  className="pl-9"
                />
                <svg 
                  xmlns="http://www.w3.org/2000/svg" 
                  className="h-4 w-4 absolute left-3 top-3 text-gray-400" 
                  fill="none" 
                  viewBox="0 0 24 24" 
                  stroke="currentColor"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
      
      {isLoading ? (
        <div className="flex justify-center items-center p-8">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
        </div>
      ) : !selectedBranchId ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center p-8 text-center">
            <Calendar className="h-16 w-16 text-gray-300 mb-4" />
            <h3 className="text-lg font-medium text-gray-700">No Branch Selected</h3>
            <p className="text-gray-500 mt-2">
              Please select a branch to view or create a daily status report.
            </p>
          </CardContent>
        </Card>
      ) : currentReportId ? (
        <>
          {/* Summary Card */}
          <div className="mb-6">
            <StatusSummaryCard
              reportId={currentReportId}
              date={selectedDate}
              specialReport={specialReport}
              onSpecialReportChange={handleSpecialReportChange}
              isEditable={canEdit}
            />
          </div>
          
          {/* Personnel Status Table */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle>Personnel Status</CardTitle>
              <Button 
                variant="secondary" 
                onClick={handleSaveReport}
                disabled={updateSpecialReportMutation.isPending}
              >
                {updateSpecialReportMutation.isPending ? "Saving..." : "Save Changes"}
              </Button>
            </CardHeader>
            <CardContent>
              <StatusTable 
                reportId={currentReportId} 
                branchId={selectedBranchId}
                isEditable={canEdit}
                onStatusUpdate={() => recheckReport()}
              />
            </CardContent>
          </Card>
        </>
      ) : (
        <Card>
          <CardContent className="flex flex-col items-center justify-center p-8 text-center">
            <Calendar className="h-16 w-16 text-gray-300 mb-4" />
            <h3 className="text-lg font-medium text-gray-700">No Report Found</h3>
            <p className="text-gray-500 mt-2">
              There is no daily status report for {formattedDate} in this branch.
            </p>
            <Button 
              className="mt-4 bg-blue-600 hover:bg-blue-700 text-white" 
              onClick={handleCreateReport}
              disabled={createReportMutation.isPending}
            >
              {createReportMutation.isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Creating...
                </>
              ) : (
                "Create Daily Report"
              )}
            </Button>
          </CardContent>
        </Card>
      )}
      
      {/* Create Report Confirmation Dialog */}
      <Dialog open={isCreatingReport} onOpenChange={setIsCreatingReport}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Create Daily Status Report</DialogTitle>
          </DialogHeader>
          <div className="py-4">
            <p>
              Are you sure you want to create a new daily status report for {formattedDate}?
            </p>
            <p className="text-sm text-gray-500 mt-2">
              This will create a new report that can be filled with personnel status information.
            </p>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsCreatingReport(false)}>
              Cancel
            </Button>
            <Button onClick={confirmCreateReport} disabled={createReportMutation.isPending}>
              {createReportMutation.isPending ? "Creating..." : "Create Report"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Layout>
  );
}
