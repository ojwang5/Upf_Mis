import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import Layout from '@/components/layout/Layout';
import { useAuth } from '@/hooks/use-auth';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { 
  Table, 
  TableBody, 
  TableCell, 
  TableHead, 
  TableHeader, 
  TableRow 
} from "@/components/ui/table";
import { 
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Loader2, FileText, Clock, Calendar, User } from 'lucide-react';

export default function HistoryPage() {
  const { user } = useAuth();
  const [branchFilter, setBranchFilter] = useState<string>('all');
  const [dateRange, setDateRange] = useState<{ start: string; end: string }>({
    start: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
    end: new Date().toISOString().split('T')[0]
  });
  
  // Fetch branches
  const { data: branches, isLoading: isLoadingBranches } = useQuery({ 
    queryKey: ['/api/branches'] 
  });

  // Fetch reports (which will serve as history records)
  const { data: reports, isLoading: isLoadingReports } = useQuery({ 
    queryKey: ['/api/protected/daily-status'] 
  });

  const isLoading = isLoadingBranches || isLoadingReports;
  const isAdmin = user?.role === 'admin';

  // Sort reports by creation date (newest first)
  const sortedReports = reports ? [...reports].sort((a: any, b: any) => 
    new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
  ) : [];

  const filteredReports = sortedReports?.filter((report: any) => {
    // Filter by branch
    if (branchFilter !== 'all') {
      const branchId = parseInt(branchFilter);
      if (report.branchId !== branchId) return false;
    }
    
    // Filter by date range
    const reportDate = new Date(report.createdAt);
    const startDate = new Date(dateRange.start);
    const endDate = new Date(dateRange.end);
    endDate.setHours(23, 59, 59, 999); // Set to end of day
    
    return reportDate >= startDate && reportDate <= endDate;
  });

  const getBranchName = (branchId: number) => {
    return branches?.find(branch => branch.id === branchId)?.name || 'Unknown Branch';
  };

  const formatTimestamp = (timestamp: string) => {
    const date = new Date(timestamp);
    return date.toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      hour12: true
    });
  };

  const getUserBranchId = () => {
    if (!user?.branchAccess) return 'all';
    const userBranch = branches?.find(branch => branch.code === user.branchAccess);
    return userBranch ? userBranch.id.toString() : 'all';
  };

  return (
    <Layout
      title="History"
      description="Track submission timeline and activity logs"
    >
      <Card className="mb-6">
        <CardHeader>
          <CardTitle>Submission Timeline</CardTitle>
          <CardDescription>
            Track the submission of daily status reports and other activities.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            {isAdmin && (
              <div className="relative">
                <label className="block text-xs font-medium text-gray-500 mb-1">Branch</label>
                <Select 
                  value={isAdmin ? branchFilter : getUserBranchId()}
                  onValueChange={setBranchFilter}
                  disabled={!isAdmin}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select branch" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Branches</SelectItem>
                    {branches?.map(branch => (
                      <SelectItem key={branch.id} value={branch.id.toString()}>
                        {branch.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}
            
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">From Date</label>
              <Input 
                type="date" 
                value={dateRange.start}
                onChange={(e) => setDateRange(prev => ({ ...prev, start: e.target.value }))}
              />
            </div>
            
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">To Date</label>
              <Input 
                type="date" 
                value={dateRange.end}
                onChange={(e) => setDateRange(prev => ({ ...prev, end: e.target.value }))}
              />
            </div>
          </div>
          
          {isLoading ? (
            <div className="flex justify-center py-8">
              <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
            </div>
          ) : filteredReports?.length > 0 ? (
            <div className="space-y-4">
              {filteredReports.map((report: any, index: number) => (
                <div key={report.id} className="bg-gray-50 rounded-lg p-4">
                  <div className="flex items-start justify-between">
                    <div className="flex items-start space-x-3">
                      <div className="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <FileText className="h-5 w-5 text-blue-600" />
                      </div>
                      <div>
                        <h4 className="text-sm font-medium text-gray-800">
                          Daily Status Report - {new Date(report.date).toLocaleDateString()}
                        </h4>
                        <p className="text-xs text-gray-500 mt-1">
                          Submitted for {getBranchName(report.branchId)}
                        </p>
                        <div className="flex items-center space-x-3 mt-2">
                          <div className="flex items-center text-xs text-gray-500">
                            <Clock className="h-3 w-3 mr-1" />
                            {formatTimestamp(report.createdAt)}
                          </div>
                          <div className="flex items-center text-xs text-gray-500">
                            <User className="h-3 w-3 mr-1" />
                            {report.submittedBy === user?.id ? 'You' : 'Another user'}
                          </div>
                        </div>
                      </div>
                    </div>
                    <Button variant="ghost" size="sm">
                      View
                    </Button>
                  </div>
                  {index < filteredReports.length - 1 && <Separator className="mt-4" />}
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-8 text-gray-500">
              <Clock className="h-12 w-12 mx-auto text-gray-300 mb-2" />
              <p>No submission history found for the selected criteria.</p>
            </div>
          )}
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader>
          <CardTitle>Recent Activities</CardTitle>
          <CardDescription>
            View recent login activities and system events.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="text-center py-8 text-gray-500">
            <Calendar className="h-12 w-12 mx-auto text-gray-300 mb-2" />
            <p>Activity log functionality coming soon.</p>
            <p className="text-sm">This feature is under development.</p>
          </div>
        </CardContent>
      </Card>
    </Layout>
  );
}
