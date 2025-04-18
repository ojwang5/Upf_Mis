import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";

interface StatusSummaryCardProps {
  reportId: number;
  date: string;
  specialReport?: string;
  onSpecialReportChange?: (value: string) => void;
  isEditable?: boolean;
}

export default function StatusSummaryCard({ 
  reportId, 
  date, 
  specialReport, 
  onSpecialReportChange,
  isEditable = false
}: StatusSummaryCardProps) {
  const { data: report, isLoading } = useQuery({ 
    queryKey: [`/api/protected/daily-status/${reportId}`],
    enabled: !!reportId
  });

  if (isLoading) {
    return <LoadingSkeleton />;
  }

  if (!report) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Daily Summary</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-red-500">No report found</div>
        </CardContent>
      </Card>
    );
  }

  const summary = report.summary;
  const formattedDate = new Date(date).toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  });

  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle>Daily Summary - {formattedDate}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-7 gap-2 text-center">
          <SummaryItem label="Total" value={summary.total} color="bg-gray-100" />
          <SummaryItem label="Present" value={summary.present} color="bg-green-100" />
          <SummaryItem label="AWOL" value={summary.awol} color="bg-red-100" />
          <SummaryItem label="Deserted" value={summary.deserted} color="bg-red-200" />
          <SummaryItem label="Sick" value={summary.sick} color="bg-amber-100" />
          <SummaryItem label="Leave" value={summary.onLeave} color="bg-blue-100" />
          <SummaryItem label="Course" value={summary.onCourse} color="bg-purple-100" />
        </div>
        
        <div className="mt-4">
          <label htmlFor="special-report" className="block text-xs font-medium text-gray-600 mb-1">
            Special Report
          </label>
          <Textarea 
            id="special-report" 
            value={specialReport || ''}
            onChange={(e) => onSpecialReportChange && onSpecialReportChange(e.target.value)}
            placeholder="Enter any special remarks for today's report..."
            rows={3}
            disabled={!isEditable}
            className="w-full"
          />
        </div>
      </CardContent>
    </Card>
  );
}

interface SummaryItemProps {
  label: string;
  value: number;
  color: string;
}

function SummaryItem({ label, value, color }: SummaryItemProps) {
  return (
    <div className={`${color} rounded p-2`}>
      <p className="text-xs text-gray-600 mb-1">{label}</p>
      <p className="text-lg font-bold text-gray-800">{value}</p>
    </div>
  );
}

function LoadingSkeleton() {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle>Daily Summary</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="animate-pulse">
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-7 gap-2">
            {[1, 2, 3, 4, 5, 6, 7].map((i) => (
              <div key={i} className="bg-gray-200 rounded p-2 h-16"></div>
            ))}
          </div>
          
          <div className="mt-4 space-y-2">
            <div className="h-4 bg-gray-200 rounded w-32"></div>
            <div className="h-24 bg-gray-200 rounded"></div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
