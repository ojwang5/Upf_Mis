import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import Layout from '@/components/layout/Layout';
import { useAuth } from '@/hooks/use-auth';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Calendar, ClipboardList } from 'lucide-react';
import { 
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import ReportHistoryTable from '@/components/history/ReportHistoryTable';

export default function HistoryPage() {
  const { user } = useAuth();
  const [branchFilter, setBranchFilter] = useState<string | null>(null);
  const [dateRange, setDateRange] = useState<{ start: string; end: string }>({
    start: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
    end: new Date().toISOString().split('T')[0]
  });
  
  // Fetch branches
  const { data: branches = [] } = useQuery<any[]>({ 
    queryKey: ['/api/branches'] 
  });

  const isAdmin = user?.role === 'admin';

  // Handle branch filter change
  const handleBranchFilterChange = (value: string) => {
    setBranchFilter(value === 'all' ? null : value);
  };

  // Get branch ID for the current user (if not admin)
  const getUserBranchId = (): string => {
    if (!user?.branchAccess) return 'all';
    const userBranch = branches.find((branch: any) => branch.code === user.branchAccess);
    return userBranch ? userBranch.id.toString() : 'all';
  };

  // Get numeric branch ID for filtering
  const getBranchIdForFilter = (): number | null => {
    if (!branchFilter || branchFilter === 'all') return null;
    return parseInt(branchFilter);
  };
  
  // Show recent activity with real-time data
  const { data: recentActivity = [] } = useQuery<any[]>({
    queryKey: ['/api/protected/activity-log'],
  });

  return (
    <Layout
      title="History"
      description="Track submission timeline and activity logs"
    >
      <Card className="mb-6">
        <CardHeader>
          <CardTitle className="flex items-center">
            <ClipboardList className="h-5 w-5 mr-2" /> 
            Report History
          </CardTitle>
          <CardDescription>
            View and export daily status reports with detailed information.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            {isAdmin && (
              <div className="relative">
                <label className="block text-xs font-medium text-gray-500 mb-1">Branch</label>
                <Select 
                  value={isAdmin ? (branchFilter || 'all') : getUserBranchId()}
                  onValueChange={handleBranchFilterChange}
                  disabled={!isAdmin}
                >
                  <SelectTrigger className="bg-white">
                    <SelectValue placeholder="Select branch" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Branches</SelectItem>
                    {branches?.map((branch: any) => (
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
                className="bg-white"
              />
            </div>
            
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">To Date</label>
              <Input 
                type="date" 
                value={dateRange.end}
                onChange={(e) => setDateRange(prev => ({ ...prev, end: e.target.value }))}
                className="bg-white"
              />
            </div>
          </div>
          
          <ReportHistoryTable 
            branchId={getBranchIdForFilter()}
            startDate={dateRange.start}
            endDate={dateRange.end}
          />
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center">
            <Calendar className="h-5 w-5 mr-2" />
            Recent Activities
          </CardTitle>
          <CardDescription>
            View recent login activities and system events.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {recentActivity.length > 0 ? (
            <div className="space-y-3">
              {recentActivity.map((activity: any) => (
                <div key={activity.id} className="p-3 bg-slate-50 rounded-md">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium">{activity.description}</p>
                      <p className="text-xs text-muted-foreground">{activity.user?.fullName || 'System'}</p>
                    </div>
                    <p className="text-xs text-muted-foreground">
                      {new Date(activity.timestamp).toLocaleString()}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-8 text-gray-500">
              <Calendar className="h-12 w-12 mx-auto text-gray-300 mb-2" />
              <p>Your recent activities will appear here.</p>
              <p className="text-sm">No recent activities found.</p>
            </div>
          )}
        </CardContent>
      </Card>
    </Layout>
  );
}
