import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import Layout from '@/components/layout/Layout';
import EmployeeTable from '@/components/employees/EmployeeTable';
import { useAuth } from '@/hooks/use-auth';
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { 
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";

export default function EmployeesPage() {
  const { user } = useAuth();
  const [branchFilter, setBranchFilter] = useState<number | undefined>(undefined);
  
  // Fetch branches
  const { data: branches, isLoading: isLoadingBranches } = useQuery({ 
    queryKey: ['/api/branches'] 
  });

  // If user is a branch manager, they can only see employees from their branch
  const isAdmin = user?.role === 'admin';
  
  const handleBranchChange = (value: string) => {
    setBranchFilter(value === 'all' ? undefined : parseInt(value));
  };

  return (
    <Layout
      title="Employee List"
      description="Manage personnel records and information"
    >
      {isAdmin && (
        <div className="flex justify-end mb-6">
          <Select 
            defaultValue="all" 
            onValueChange={handleBranchChange}
          >
            <SelectTrigger className="w-[200px]">
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
      
      <Tabs defaultValue="all">
        <TabsList className="mb-6">
          <TabsTrigger value="all">All Personnel</TabsTrigger>
          <TabsTrigger value="officers">Officers</TabsTrigger>
          <TabsTrigger value="ncos">NCOs</TabsTrigger>
          <TabsTrigger value="constables">Constables</TabsTrigger>
        </TabsList>
        
        <TabsContent value="all">
          <Card>
            <CardHeader className="pb-0">
              <CardTitle>All Personnel</CardTitle>
            </CardHeader>
            <CardContent>
              <EmployeeTable branchId={isAdmin ? branchFilter : getBranchIdFromUser(user, branches)} />
            </CardContent>
          </Card>
        </TabsContent>
        
        <TabsContent value="officers">
          <Card>
            <CardHeader className="pb-0">
              <CardTitle>Officers</CardTitle>
            </CardHeader>
            <CardContent>
              <EmployeeTable branchId={isAdmin ? branchFilter : getBranchIdFromUser(user, branches)} />
            </CardContent>
          </Card>
        </TabsContent>
        
        <TabsContent value="ncos">
          <Card>
            <CardHeader className="pb-0">
              <CardTitle>NCOs</CardTitle>
            </CardHeader>
            <CardContent>
              <EmployeeTable branchId={isAdmin ? branchFilter : getBranchIdFromUser(user, branches)} />
            </CardContent>
          </Card>
        </TabsContent>
        
        <TabsContent value="constables">
          <Card>
            <CardHeader className="pb-0">
              <CardTitle>Constables</CardTitle>
            </CardHeader>
            <CardContent>
              <EmployeeTable branchId={isAdmin ? branchFilter : getBranchIdFromUser(user, branches)} />
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </Layout>
  );
}

// Helper function to get the branch ID from user data
function getBranchIdFromUser(user: any, branches: any[] = []) {
  if (!user?.branchAccess) return undefined;
  
  const userBranch = branches.find(branch => branch.code === user.branchAccess);
  return userBranch?.id;
}
