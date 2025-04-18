import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { 
  Table, 
  TableBody, 
  TableCell, 
  TableHead, 
  TableHeader, 
  TableRow 
} from "@/components/ui/table";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { 
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue, 
} from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { MoreVertical, RefreshCw, Search, Edit, FileText, Trash2 } from 'lucide-react';
import EmployeeForm from './EmployeeForm';

interface Employee {
  id: number;
  fileNumber: string;
  fullName: string;
  gender: string;
  rank: string;
  instrument: string;
  role: string;
  supervisorType: string;
  dateJoined: string;
  phone: string;
  email: string;
  branchId: number;
  branchName?: string;
  supervisorId?: number;
}

interface Branch {
  id: number;
  name: string;
  code: string;
  location: string;
}

interface EmployeeTableProps {
  branchId?: number;
}

export default function EmployeeTable({ branchId }: EmployeeTableProps) {
  const [searchTerm, setSearchTerm] = useState('');
  const [supervisorFilter, setSupervisorFilter] = useState('all');
  const [currentPage, setCurrentPage] = useState(1);
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [selectedEmployee, setSelectedEmployee] = useState<Employee | null>(null);
  const itemsPerPage = 10;

  // Fetch employees
  const { data: employees, isLoading: isLoadingEmployees } = useQuery<Employee[]>({ 
    queryKey: ['/api/protected/employees'] 
  });

  // Fetch branches
  const { data: branches } = useQuery<Branch[]>({ 
    queryKey: ['/api/branches'] 
  });

  // Filter employees
  const filteredEmployees = employees?.filter(employee => {
    let passesSearch = true;
    let passesSupervisor = true;
    let passesBranch = true;

    // Apply search filter
    if (searchTerm) {
      passesSearch = 
        employee.fullName.toLowerCase().includes(searchTerm.toLowerCase()) ||
        employee.fileNumber.toLowerCase().includes(searchTerm.toLowerCase());
    }

    // Apply supervisor filter
    if (supervisorFilter !== 'all') {
      passesSupervisor = employee.supervisorType === supervisorFilter;
    }

    // Apply branch filter
    if (branchId) {
      passesBranch = employee.branchId === branchId;
    }

    return passesSearch && passesSupervisor && passesBranch;
  }) || [];

  // Pagination
  const totalPages = Math.ceil(filteredEmployees.length / itemsPerPage);
  const paginatedEmployees = filteredEmployees.slice(
    (currentPage - 1) * itemsPerPage,
    currentPage * itemsPerPage
  );

  const handleCreate = () => {
    setSelectedEmployee(null);
    setIsCreateDialogOpen(true);
  };

  const handleEdit = (employee: Employee) => {
    setSelectedEmployee(employee);
    setIsEditDialogOpen(true);
  };

  const getBranchName = (branchId: number) => {
    return branches?.find(branch => branch.id === branchId)?.name || 'Unknown Branch';
  };

  const getSupervisorTypeBadge = (type: string) => {
    switch(type) {
      case 'officer':
        return <Badge className="bg-blue-100 text-blue-800">Officer</Badge>;
      case 'nco':
        return <Badge className="bg-amber-100 text-amber-800">NCO</Badge>;
      case 'constable':
        return <Badge className="bg-green-100 text-green-800">Constable</Badge>;
      default:
        return <Badge>{type}</Badge>;
    }
  };

  if (isLoadingEmployees) {
    return <div className="flex justify-center p-8"><RefreshCw className="animate-spin h-8 w-8 text-blue-600" /></div>;
  }

  return (
    <div>
      <div className="flex flex-col sm:flex-row space-y-2 sm:space-y-0 justify-between mb-4">
        <div className="flex items-center space-x-2">
          <div className="relative w-full sm:w-64">
            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-gray-500" />
            <Input
              placeholder="Search by name or ID..."
              className="pl-8"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          
          <Select
            value={supervisorFilter}
            onValueChange={setSupervisorFilter}
          >
            <SelectTrigger className="w-[180px]">
              <SelectValue placeholder="Filter by supervisor" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Types</SelectItem>
              <SelectItem value="officer">Officers</SelectItem>
              <SelectItem value="nco">NCOs</SelectItem>
              <SelectItem value="constable">Constables</SelectItem>
            </SelectContent>
          </Select>
        </div>
        
        <Button onClick={handleCreate}>Add Employee</Button>
      </div>
      
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>File No.</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Rank</TableHead>
              <TableHead>Supervisor Type</TableHead>
              <TableHead>Instrument</TableHead>
              {!branchId && <TableHead>Branch</TableHead>}
              <TableHead>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {paginatedEmployees.length > 0 ? (
              paginatedEmployees.map((employee) => (
                <TableRow key={employee.id}>
                  <TableCell className="font-medium">{employee.fileNumber}</TableCell>
                  <TableCell>
                    <div>
                      <div className="font-medium">{employee.fullName}</div>
                      <div className="text-sm text-gray-500">{employee.gender === 'male' ? 'Male' : 'Female'}</div>
                    </div>
                  </TableCell>
                  <TableCell>{employee.rank}</TableCell>
                  <TableCell>
                    {getSupervisorTypeBadge(employee.supervisorType)}
                  </TableCell>
                  <TableCell>{employee.instrument || '-'}</TableCell>
                  {!branchId && (
                    <TableCell>{getBranchName(employee.branchId)}</TableCell>
                  )}
                  <TableCell>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon">
                          <MoreVertical className="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuItem onClick={() => handleEdit(employee)}>
                          <Edit className="mr-2 h-4 w-4" />
                          <span>Edit</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem>
                          <FileText className="mr-2 h-4 w-4" />
                          <span>View Details</span>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem>
                          <Trash2 className="mr-2 h-4 w-4 text-red-600" />
                          <span className="text-red-600">Delete</span>
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={branchId ? 6 : 7} className="h-24 text-center">
                  No employees found.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      {totalPages > 1 && (
        <div className="flex justify-between items-center mt-4">
          <div className="text-sm text-gray-500">
            Showing {(currentPage - 1) * itemsPerPage + 1} to {Math.min(currentPage * itemsPerPage, filteredEmployees.length)} of {filteredEmployees.length} employees
          </div>
          <div className="flex space-x-1">
            <Button
              variant="outline"
              size="icon"
              onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
              disabled={currentPage === 1}
            >
              &lt;
            </Button>
            {Array.from({ length: totalPages }, (_, i) => i + 1).map(page => (
              <Button
                key={page}
                variant={page === currentPage ? "default" : "outline"}
                size="icon"
                onClick={() => setCurrentPage(page)}
              >
                {page}
              </Button>
            ))}
            <Button
              variant="outline"
              size="icon"
              onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
              disabled={currentPage === totalPages}
            >
              &gt;
            </Button>
          </div>
        </div>
      )}

      {/* Create Employee Dialog */}
      <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
        <DialogContent className="sm:max-w-[600px]">
          <DialogHeader>
            <DialogTitle>Create New Employee</DialogTitle>
          </DialogHeader>
          <EmployeeForm 
            branches={branches || []} 
            onSuccess={() => setIsCreateDialogOpen(false)}
            defaultBranchId={branchId}
          />
        </DialogContent>
      </Dialog>

      {/* Edit Employee Dialog */}
      <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
        <DialogContent className="sm:max-w-[600px]">
          <DialogHeader>
            <DialogTitle>Edit Employee</DialogTitle>
          </DialogHeader>
          <EmployeeForm 
            employee={selectedEmployee}
            branches={branches || []} 
            onSuccess={() => setIsEditDialogOpen(false)}
          />
        </DialogContent>
      </Dialog>
    </div>
  );
}
