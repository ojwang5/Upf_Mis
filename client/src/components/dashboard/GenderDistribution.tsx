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

export default function GenderDistribution() {
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
          <CardTitle>Gender Distribution</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-red-500">
            Error loading gender data: {error.message}
          </div>
        </CardContent>
      </Card>
    );
  }

  // Calculate total by gender across all branches
  const totalMale = summaries?.reduce((acc, curr) => acc + curr.summary.maleCount, 0) || 0;
  const totalFemale = summaries?.reduce((acc, curr) => acc + curr.summary.femaleCount, 0) || 0;
  const totalPersonnel = totalMale + totalFemale;
  
  const malePercentage = totalPersonnel > 0 ? (totalMale / totalPersonnel) * 100 : 0;
  const femalePercentage = totalPersonnel > 0 ? (totalFemale / totalPersonnel) * 100 : 0;

  return (
    <Card>
      <CardHeader className="pb-0">
        <CardTitle>Gender Distribution</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="flex justify-center items-center h-64">
          <div className="w-full max-w-xs">
            <div className="relative pt-4">
              <div className="flex justify-between mb-2">
                <span className="text-sm text-gray-600">Male</span>
                <span className="text-sm font-medium text-gray-800">
                  {totalMale} ({malePercentage.toFixed(1)}%)
                </span>
              </div>
              <Progress value={malePercentage} className="h-4 bg-gray-200" indicatorClassName="bg-blue-600" />
            </div>
            
            <div className="relative pt-4">
              <div className="flex justify-between mb-2">
                <span className="text-sm text-gray-600">Female</span>
                <span className="text-sm font-medium text-gray-800">
                  {totalFemale} ({femalePercentage.toFixed(1)}%)
                </span>
              </div>
              <Progress value={femalePercentage} className="h-4 bg-gray-200" indicatorClassName="bg-amber-500" />
            </div>
            
            <div className="mt-6 pt-6 border-t border-gray-200">
              <div className="grid grid-cols-2 gap-4">
                <div className="text-center">
                  <p className="text-sm text-gray-600">Total Male</p>
                  <p className="text-xl font-bold text-gray-800">{totalMale}</p>
                </div>
                <div className="text-center">
                  <p className="text-sm text-gray-600">Total Female</p>
                  <p className="text-xl font-bold text-gray-800">{totalFemale}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function LoadingSkeleton() {
  return (
    <Card>
      <CardHeader className="pb-0">
        <CardTitle>Gender Distribution</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="animate-pulse space-y-8 flex justify-center items-center h-64">
          <div className="w-full max-w-xs space-y-6">
            <div className="space-y-2">
              <div className="flex justify-between">
                <div className="h-4 bg-gray-200 rounded w-16"></div>
                <div className="h-4 bg-gray-200 rounded w-20"></div>
              </div>
              <div className="h-4 bg-gray-200 rounded w-full"></div>
            </div>
            
            <div className="space-y-2">
              <div className="flex justify-between">
                <div className="h-4 bg-gray-200 rounded w-16"></div>
                <div className="h-4 bg-gray-200 rounded w-20"></div>
              </div>
              <div className="h-4 bg-gray-200 rounded w-full"></div>
            </div>
            
            <div className="pt-6 border-t border-gray-200">
              <div className="grid grid-cols-2 gap-4">
                <div className="text-center space-y-2">
                  <div className="h-4 bg-gray-200 rounded mx-auto w-20"></div>
                  <div className="h-6 bg-gray-200 rounded mx-auto w-8"></div>
                </div>
                <div className="text-center space-y-2">
                  <div className="h-4 bg-gray-200 rounded mx-auto w-20"></div>
                  <div className="h-6 bg-gray-200 rounded mx-auto w-8"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
