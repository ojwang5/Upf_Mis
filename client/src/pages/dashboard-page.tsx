import { useState } from 'react';
import Layout from '@/components/layout/Layout';
import StatCard from '@/components/dashboard/StatCard';
import BranchOverview from '@/components/dashboard/BranchOverview';
import GenderDistribution from '@/components/dashboard/GenderDistribution';
import StatusSummary from '@/components/dashboard/StatusSummary';
import RecentActivity from '@/components/dashboard/RecentActivity';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '@/hooks/use-auth';
import { 
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Loader2, UserCheck, UserX, Calendar, Activity } from 'lucide-react';

export default function DashboardPage() {
  const { user } = useAuth();
  const [branchFilter, setBranchFilter] = useState<string>('all');
  const [dateFilter, setDateFilter] = useState<string>(new Date().toISOString().split('T')[0]);
  
  // Fetch branch summary data
  const { data: summaries, isLoading, error } = useQuery({ 
    queryKey: ['/api/protected/summary'] 
  });

  // Calculate totals for stat cards
  const totals = summaries?.reduce((acc, curr) => {
    return {
      total: acc.total + curr.summary.total,
      present: acc.present + curr.summary.present,
      awol: acc.awol + curr.summary.awol,
      onLeave: acc.onLeave + curr.summary.onLeave,
      sick: acc.sick + curr.summary.sick
    };
  }, {
    total: 0,
    present: 0,
    awol: 0,
    onLeave: 0,
    sick: 0
  }) || { total: 0, present: 0, awol: 0, onLeave: 0, sick: 0 };

  return (
    <Layout 
      title="Dashboard" 
      description="Overview of personnel statistics and daily status"
    >
      <div className="flex flex-col md:flex-row md:items-center justify-end space-y-2 md:space-y-0 md:space-x-2 mb-6">
        <div className="relative">
          <Select 
            value={branchFilter}
            onValueChange={setBranchFilter}
          >
            <SelectTrigger className="w-[180px]">
              <SelectValue placeholder="Select branch" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Branches</SelectItem>
              <SelectItem value="kampala">Kampala HQ</SelectItem>
              <SelectItem value="rwizi">Rwizi Mbarara</SelectItem>
              <SelectItem value="nkyoga">N.Kyoga Lira</SelectItem>
            </SelectContent>
          </Select>
        </div>
        
        <div className="relative">
          <Input
            type="date"
            value={dateFilter}
            onChange={(e) => setDateFilter(e.target.value)}
            className="w-[180px]"
          />
        </div>
      </div>
      
      {/* Stats Cards */}
      {isLoading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="bg-white rounded-lg shadow-sm p-4 border-l-4 border-gray-300 animate-pulse">
              <div className="flex justify-between items-start">
                <div className="space-y-2">
                  <div className="h-4 bg-gray-200 rounded w-16"></div>
                  <div className="h-6 bg-gray-300 rounded w-12"></div>
                  <div className="h-3 bg-gray-200 rounded w-24"></div>
                </div>
                <div className="h-8 w-8 bg-gray-200 rounded-full"></div>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <StatCard 
            title="Present" 
            value={totals.present}
            icon={<UserCheck />}
            change="+5% from yesterday"
            color="present"
          />
          
          <StatCard 
            title="AWOL" 
            value={totals.awol}
            icon={<UserX />}
            change="+1 from yesterday"
            color="absent"
          />
          
          <StatCard 
            title="On Leave" 
            value={totals.onLeave}
            icon={<Calendar />}
            change="No change"
            color="leave"
          />
          
          <StatCard 
            title="Sick" 
            value={totals.sick}
            icon={<Activity />}
            change="+2 from yesterday"
            color="sick"
          />
        </div>
      )}
      
      {/* Branch Overview */}
      <div className="mb-6">
        <BranchOverview />
      </div>
      
      {/* Gender Distribution and Status Summary */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <GenderDistribution />
        <StatusSummary />
      </div>
      
      {/* Recent Activity */}
      <div className="mb-6">
        <RecentActivity />
      </div>
    </Layout>
  );
}
