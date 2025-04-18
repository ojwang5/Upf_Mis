import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Calendar, Users, AlertCircle, FileText, CheckCircle } from 'lucide-react';

interface Activity {
  id: number;
  type: 'report_submitted' | 'employee_added' | 'awol_alert' | 'leave_approved' | 'other';
  message: string;
  timestamp: string;
  userFullName: string;
  branchName?: string;
}

// This is a static component for now since we don't have a real activity endpoint
export default function RecentActivity() {
  // In a real implementation, this would fetch from an API
  const activities: Activity[] = [
    {
      id: 1,
      type: 'report_submitted',
      message: 'Daily status report submitted for Kampala HQ',
      timestamp: new Date().toISOString(),
      userFullName: 'Admin User'
    },
    {
      id: 2,
      type: 'employee_added',
      message: 'New employee added: Sarah Namukasa',
      timestamp: new Date(Date.now() - 86400000).toISOString(), // Yesterday
      userFullName: 'Kampala Manager',
      branchName: 'Kampala HQ'
    },
    {
      id: 3,
      type: 'awol_alert',
      message: 'AWOL alert: John Mukasa has been absent for 2 days',
      timestamp: new Date(Date.now() - 86400000).toISOString(), // Yesterday
      userFullName: 'System'
    },
    {
      id: 4,
      type: 'leave_approved',
      message: 'Leave approved for David Ochieng (Annual Leave)',
      timestamp: new Date(Date.now() - 172800000).toISOString(), // 2 days ago
      userFullName: 'Rwizi Manager',
      branchName: 'Rwizi Mbarara'
    }
  ];

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'report_submitted':
        return <FileText className="h-5 w-5 text-blue-600" />;
      case 'employee_added':
        return <Users className="h-5 w-5 text-amber-600" />;
      case 'awol_alert':
        return <AlertCircle className="h-5 w-5 text-red-600" />;
      case 'leave_approved':
        return <CheckCircle className="h-5 w-5 text-green-600" />;
      default:
        return <Calendar className="h-5 w-5 text-gray-600" />;
    }
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    
    // Today
    if (date.toDateString() === now.toDateString()) {
      return `Today, ${date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`;
    }
    
    // Yesterday
    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);
    if (date.toDateString() === yesterday.toDateString()) {
      return `Yesterday, ${date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`;
    }
    
    // Older dates
    return date.toLocaleDateString([], { month: 'short', day: 'numeric' }) + 
           `, ${date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`;
  };

  return (
    <Card>
      <CardHeader className="pb-0 flex flex-row items-center justify-between">
        <CardTitle>Recent Activity</CardTitle>
        <Button variant="link" size="sm">View All</Button>
      </CardHeader>
      <CardContent>
        <ul className="space-y-4 mt-4">
          {activities.map((activity) => (
            <li key={activity.id} className="flex">
              <div className="flex-shrink-0 w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center mr-3">
                {getActivityIcon(activity.type)}
              </div>
              <div>
                <p className="text-sm text-gray-800 font-medium">{activity.message}</p>
                <p className="text-xs text-gray-600 mt-1">
                  {formatDate(activity.timestamp)} by{' '}
                  <span className="text-blue-600">{activity.userFullName}</span>
                  {activity.branchName && ` (${activity.branchName})`}
                </p>
              </div>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}
