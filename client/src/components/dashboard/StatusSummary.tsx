import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";

interface BranchSummary {
  branch: {
    id: number;
    name: string;
    code: string;
    location: string;
  };
  summary: {
    total: number;
    present: number;
    sick: number;
    awol: number;
    deserted: number;
    onLeave: number;
    onCourse: number;
    onSuspension: number;
    maleCount: number;
    femaleCount: number;
  };
}

export default function StatusSummary() {
  const { data: summaries, isLoading, error } = useQuery<BranchSummary[]>({ 
    queryKey: ['/api/protected/summary'] 
  });

  if (isLoading) {
    return <LoadingSkeleton />;
  }

  if (error) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Status Summary</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-red-500">
            Error loading status data: {error.message}
          </div>
        </CardContent>
      </Card>
    );
  }

  // Calculate totals across all branches
  const totalPersonnel = summaries?.reduce((acc, curr) => acc + curr.summary.total, 0) || 0;
  const present = summaries?.reduce((acc, curr) => acc + curr.summary.present, 0) || 0;
  const sick = summaries?.reduce((acc, curr) => acc + curr.summary.sick, 0) || 0;
  const awol = summaries?.reduce((acc, curr) => acc + curr.summary.awol, 0) || 0;
  const deserted = summaries?.reduce((acc, curr) => acc + curr.summary.deserted, 0) || 0;
  const onLeave = summaries?.reduce((acc, curr) => acc + curr.summary.onLeave, 0) || 0;
  const onCourse = summaries?.reduce((acc, curr) => acc + curr.summary.onCourse, 0) || 0;
  
  // Calculate percentages
  const presentPercentage = totalPersonnel > 0 ? (present / totalPersonnel) * 100 : 0;
  const sickPercentage = totalPersonnel > 0 ? (sick / totalPersonnel) * 100 : 0;
  const awolPercentage = totalPersonnel > 0 ? (awol / totalPersonnel) * 100 : 0;
  const onLeavePercentage = totalPersonnel > 0 ? (onLeave / totalPersonnel) * 100 : 0;
  const onCoursePercentage = totalPersonnel > 0 ? (onCourse / totalPersonnel) * 100 : 0;

  return (
    <Card>
      <CardHeader className="pb-0">
        <CardTitle>Status Summary</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <StatusItem 
            label="Present" 
            count={present} 
            percentage={presentPercentage.toFixed(1)} 
            bgColor="bg-green-100" 
            textColor="text-green-700" 
          />
          
          <StatusItem 
            label="AWOL" 
            count={awol} 
            percentage={awolPercentage.toFixed(1)} 
            bgColor="bg-red-100" 
            textColor="text-red-700" 
          />
          
          <StatusItem 
            label="On Leave" 
            count={onLeave} 
            percentage={onLeavePercentage.toFixed(1)} 
            bgColor="bg-blue-100" 
            textColor="text-blue-700" 
          />
          
          <StatusItem 
            label="Sick" 
            count={sick} 
            percentage={sickPercentage.toFixed(1)} 
            bgColor="bg-amber-100" 
            textColor="text-amber-700" 
          />
          
          <StatusItem 
            label="On Course" 
            count={onCourse} 
            percentage={onCoursePercentage.toFixed(1)} 
            bgColor="bg-purple-100" 
            textColor="text-purple-700" 
          />
          
          <div className="bg-blue-900 text-white rounded-lg p-3 text-center">
            <p className="text-xs opacity-80">Total Staff</p>
            <p className="text-xl font-bold">{totalPersonnel}</p>
            <p className="text-xs opacity-80">100%</p>
          </div>
        </div>
        
        <div className="mt-6">
          <h3 className="text-sm font-medium text-gray-800 mb-3">Staff by Supervisor Type</h3>
          <div className="space-y-3">
            <SupervisorBar 
              label="Officers" 
              percentage={14} 
              count={15} 
              total={totalPersonnel}
              barColor="bg-blue-900" 
            />
            
            <SupervisorBar 
              label="NCOs" 
              percentage={39.3} 
              count={42} 
              total={totalPersonnel}
              barColor="bg-blue-600" 
            />
            
            <SupervisorBar 
              label="Constables" 
              percentage={46.7} 
              count={50} 
              total={totalPersonnel}
              barColor="bg-amber-500" 
            />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

interface StatusItemProps {
  label: string;
  count: number;
  percentage: string;
  bgColor: string;
  textColor: string;
}

function StatusItem({ label, count, percentage, bgColor, textColor }: StatusItemProps) {
  return (
    <div className={`${bgColor} rounded-lg p-3 text-center`}>
      <p className="text-xs text-gray-600">{label}</p>
      <p className={`text-xl font-bold ${textColor}`}>{count}</p>
      <p className="text-xs text-gray-600">{percentage}%</p>
    </div>
  );
}

interface SupervisorBarProps {
  label: string;
  percentage: number;
  count: number;
  total: number;
  barColor: string;
}

function SupervisorBar({ label, percentage, count, total, barColor }: SupervisorBarProps) {
  return (
    <div className="relative">
      <div className="flex justify-between mb-1">
        <span className="text-xs text-gray-600">{label}</span>
        <span className="text-xs font-medium text-gray-800">
          {count} ({percentage.toFixed(1)}%)
        </span>
      </div>
      <Progress value={percentage} className="h-2 bg-gray-200" indicatorClassName={barColor} />
    </div>
  );
}

function LoadingSkeleton() {
  return (
    <Card>
      <CardHeader className="pb-0">
        <CardTitle>Status Summary</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="animate-pulse">
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
            {[1, 2, 3, 4, 5, 6].map((i) => (
              <div key={i} className="bg-gray-200 rounded-lg p-3 h-20"></div>
            ))}
          </div>
          
          <div className="mt-6 space-y-6">
            <div className="h-4 bg-gray-200 rounded w-48"></div>
            <div className="space-y-3">
              {[1, 2, 3].map((i) => (
                <div key={i} className="space-y-2">
                  <div className="flex justify-between">
                    <div className="h-3 bg-gray-200 rounded w-20"></div>
                    <div className="h-3 bg-gray-200 rounded w-24"></div>
                  </div>
                  <div className="h-2 bg-gray-200 rounded w-full"></div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
