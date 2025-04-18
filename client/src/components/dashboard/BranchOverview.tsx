import { useQuery } from '@tanstack/react-query';
import { 
  Table, 
  TableBody, 
  TableCell, 
  TableHead, 
  TableHeader, 
  TableRow 
} from "@/components/ui/table";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

interface BranchSummary {
  branch: {
    id: number;
    name: string;
    code: string;
    location: string;
  };
  report?: {
    id: number;
    date: string;
    branchId: number;
    submittedBy: number;
    specialReport?: string;
    createdAt: string;
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

export default function BranchOverview() {
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
          <CardTitle>Branch Overview</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-red-500">
            Error loading branch data: {error.message}
          </div>
        </CardContent>
      </Card>
    );
  }

  // Calculate totals
  const totals = summaries?.reduce((acc, curr) => {
    return {
      total: acc.total + curr.summary.total,
      present: acc.present + curr.summary.present,
      awol: acc.awol + curr.summary.awol,
      onLeave: acc.onLeave + curr.summary.onLeave,
      sick: acc.sick + curr.summary.sick,
      onCourse: acc.onCourse + curr.summary.onCourse,
      deserted: acc.deserted + curr.summary.deserted
    };
  }, {
    total: 0,
    present: 0,
    awol: 0,
    onLeave: 0,
    sick: 0,
    onCourse: 0,
    deserted: 0
  });

  return (
    <Card>
      <CardHeader className="pb-0">
        <CardTitle>Branch Overview</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Branch</TableHead>
                <TableHead>Total</TableHead>
                <TableHead>Present</TableHead>
                <TableHead>AWOL</TableHead>
                <TableHead>On Leave</TableHead>
                <TableHead>Sick</TableHead>
                <TableHead>On Course</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {summaries?.map((summary) => (
                <TableRow key={summary.branch.id}>
                  <TableCell className="font-medium">{summary.branch.name}</TableCell>
                  <TableCell>{summary.summary.total}</TableCell>
                  <TableCell>{summary.summary.present}</TableCell>
                  <TableCell>{summary.summary.awol}</TableCell>
                  <TableCell>{summary.summary.onLeave}</TableCell>
                  <TableCell>{summary.summary.sick}</TableCell>
                  <TableCell>{summary.summary.onCourse}</TableCell>
                </TableRow>
              ))}
              <TableRow className="bg-neutral-50 font-medium">
                <TableCell className="font-bold">TOTAL</TableCell>
                <TableCell>{totals?.total}</TableCell>
                <TableCell>{totals?.present}</TableCell>
                <TableCell>{totals?.awol}</TableCell>
                <TableCell>{totals?.onLeave}</TableCell>
                <TableCell>{totals?.sick}</TableCell>
                <TableCell>{totals?.onCourse}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
  );
}

function LoadingSkeleton() {
  return (
    <Card>
      <CardHeader className="pb-0">
        <CardTitle>Branch Overview</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="animate-pulse space-y-4">
          <div className="h-10 bg-gray-200 rounded"></div>
          <div className="space-y-2">
            {[1, 2, 3].map((i) => (
              <div key={i} className="h-8 bg-gray-200 rounded"></div>
            ))}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
