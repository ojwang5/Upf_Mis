import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import Layout from '@/components/layout/Layout';
import { useAuth } from '@/hooks/use-auth';
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
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
import { Loader2, FileText, Download } from 'lucide-react';
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs";

export default function ReportsPage() {
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

  // Fetch reports
  const { data: reports, isLoading: isLoadingReports } = useQuery({ 
    queryKey: ['/api/protected/daily-status'] 
  });

  const isLoading = isLoadingBranches || isLoadingReports;
  const isAdmin = user?.role === 'admin';

  const filteredReports = reports?.filter((report: any) => {
    // Filter by branch
    if (branchFilter !== 'all') {
      const branchId = parseInt(branchFilter);
      if (report.branchId !== branchId) return false;
    }
    
    // Filter by date range
    const reportDate = new Date(report.date);
    const startDate = new Date(dateRange.start);
    const endDate = new Date(dateRange.end);
    return reportDate >= startDate && reportDate <= endDate;
  });

  const getBranchName = (branchId: number) => {
    return branches?.find(branch => branch.id === branchId)?.name || 'Unknown Branch';
  };

  const getUserBranchId = () => {
    if (!user?.branchAccess) return 'all';
    const userBranch = branches?.find(branch => branch.code === user.branchAccess);
    return userBranch ? userBranch.id.toString() : 'all';
  };

  return (
    <Layout
      title="Reports"
      description="Generate summaries and analytics"
    >
      <div className="mb-6">
        <Tabs defaultValue="daily">
          <TabsList>
            <TabsTrigger value="daily">Daily Reports</TabsTrigger>
            <TabsTrigger value="monthly">Monthly Reports</TabsTrigger>
            <TabsTrigger value="summary">Summary Reports</TabsTrigger>
          </TabsList>
          
          <TabsContent value="daily" className="mt-6">
            <Card>
              <CardHeader className="pb-0">
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center">
                  <CardTitle>Daily Status Reports</CardTitle>
                  <div className="flex space-x-2 mt-4 md:mt-0">
                    <Button variant="outline">
                      <Download className="mr-2 h-4 w-4" />
                      Export to PDF
                    </Button>
                    <Button variant="outline">
                      <Download className="mr-2 h-4 w-4" />
                      Export to CSV
                    </Button>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 mt-4">
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
                  <div className="rounded-md border">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Date</TableHead>
                          <TableHead>Branch</TableHead>
                          <TableHead>Total</TableHead>
                          <TableHead>Present</TableHead>
                          <TableHead>AWOL</TableHead>
                          <TableHead>On Leave</TableHead>
                          <TableHead>Actions</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {filteredReports.map((report: any) => (
                          <TableRow key={report.id}>
                            <TableCell>{new Date(report.date).toLocaleDateString()}</TableCell>
                            <TableCell>{getBranchName(report.branchId)}</TableCell>
                            <TableCell>42</TableCell>
                            <TableCell>36</TableCell>
                            <TableCell>1</TableCell>
                            <TableCell>5</TableCell>
                            <TableCell>
                              <div className="flex space-x-2">
                                <Button variant="ghost" size="sm">
                                  <FileText className="h-4 w-4 mr-1" />
                                  View
                                </Button>
                                <Button variant="ghost" size="sm">
                                  <Download className="h-4 w-4 mr-1" />
                                  Export
                                </Button>
                              </div>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                ) : (
                  <div className="text-center py-8 text-gray-500">
                    <FileText className="h-12 w-12 mx-auto text-gray-300 mb-2" />
                    <p>No reports found for the selected criteria.</p>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>
          
          <TabsContent value="monthly" className="mt-6">
            <Card>
              <CardHeader>
                <CardTitle>Monthly Reports</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-center py-8 text-gray-500">
                  <FileText className="h-12 w-12 mx-auto text-gray-300 mb-2" />
                  <p>Monthly report functionality coming soon.</p>
                  <p className="text-sm">This feature is under development.</p>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
          
          <TabsContent value="summary" className="mt-6">
            <Card>
              <CardHeader>
                <CardTitle>Summary Reports</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-center py-8 text-gray-500">
                  <FileText className="h-12 w-12 mx-auto text-gray-300 mb-2" />
                  <p>Summary report functionality coming soon.</p>
                  <p className="text-sm">This feature is under development.</p>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </Layout>
  );
}
