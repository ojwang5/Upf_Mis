import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { 
  Table, 
  TableBody, 
  TableCell, 
  TableHead, 
  TableHeader, 
  TableRow 
} from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { 
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogTrigger
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { apiRequest, queryClient } from '@/lib/queryClient';
import { useToast } from '@/hooks/use-toast';
import { Edit, MoreVertical } from 'lucide-react';

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
  supervisorId?: number;
}

interface StatusEntry {
  id: number;
  reportId: number;
  employeeId: number;
  status: string;
  remarks?: string;
  employee: Employee;
}

interface StatusTableProps {
  reportId: number;
  branchId: number;
  isEditable?: boolean;
  onStatusUpdate?: () => void;
}

export default function StatusTable({ reportId, branchId, isEditable = true, onStatusUpdate }: StatusTableProps) {
  const [currentPage, setCurrentPage] = useState(1);
  const [editingEntry, setEditingEntry] = useState<StatusEntry | null>(null);
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const entriesPerPage = 10;
  const { toast } = useToast();

  // Fetch report with entries
  const { data: report, isLoading, error } = useQuery({ 
    queryKey: [`/api/protected/daily-status/${reportId}`],
    enabled: !!reportId
  });

  // Fetch employees that don't have entries yet
  const { data: employees } = useQuery({ 
    queryKey: ['/api/protected/employees'],
    enabled: !!branchId
  });

  // Mutation to update entry
  const updateEntryMutation = useMutation({
    mutationFn: async (data: { id: number, status: string, remarks?: string }) => {
      return await apiRequest(
        'PATCH', 
        `/api/protected/daily-status/entries/${data.id}`, 
        { 
          status: data.status, 
          remarks: data.remarks,
          reportId // Need to include reportId for authorization check
        }
      );
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [`/api/protected/daily-status/${reportId}`] });
      setIsEditDialogOpen(false);
      toast({
        title: "Status updated",
        description: "The personnel status has been updated successfully.",
      });
      if (onStatusUpdate) onStatusUpdate();
    },
    onError: (error) => {
      toast({
        title: "Update failed",
        description: error.message,
        variant: "destructive",
      });
    }
  });

  // Mutation to add entry
  const addEntryMutation = useMutation({
    mutationFn: async (data: { employeeId: number, status: string, remarks?: string }) => {
      return await apiRequest(
        'POST', 
        `/api/protected/daily-status/${reportId}/entries`, 
        { 
          employeeId: data.employeeId,
          status: data.status, 
          remarks: data.remarks 
        }
      );
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [`/api/protected/daily-status/${reportId}`] });
      setIsEditDialogOpen(false);
      toast({
        title: "Status added",
        description: "The personnel status has been added successfully.",
      });
      if (onStatusUpdate) onStatusUpdate();
    },
    onError: (error) => {
      toast({
        title: "Add failed",
        description: error.message,
        variant: "destructive",
      });
    }
  });

  const handleSubmitEdit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingEntry) return;

    const formData = new FormData(e.target as HTMLFormElement);
    const status = formData.get('status') as string;
    const remarks = formData.get('remarks') as string;

    if (editingEntry.id) {
      // Update existing entry
      updateEntryMutation.mutate({
        id: editingEntry.id,
        status,
        remarks
      });
    } else if (editingEntry.employeeId) {
      // Add new entry
      addEntryMutation.mutate({
        employeeId: editingEntry.employeeId,
        status,
        remarks
      });
    }
  };

  const handleEditClick = (entry: StatusEntry) => {
    setEditingEntry(entry);
    setIsEditDialogOpen(true);
  };

  const handleAddClick = (employee: Employee) => {
    setEditingEntry({
      id: 0, // No ID yet
      reportId,
      employeeId: employee.id,
      status: 'present', // Default status
      employee
    });
    setIsEditDialogOpen(true);
  };

  if (isLoading) {
    return <div className="text-center py-4">Loading personnel status...</div>;
  }

  if (error) {
    return <div className="text-red-500 text-center py-4">Error loading status: {error.message}</div>;
  }

  if (!report || !report.entries) {
    return <div className="text-center py-4">No status report found</div>;
  }

  const entries = report.entries;
  
  // Filter out employees that already have entries
  const employeeIdsWithEntries = entries.map(entry => entry.employee.id);
  const availableEmployees = employees?.filter(
    emp => !employeeIdsWithEntries.includes(emp.id) && emp.branchId === branchId
  ) || [];

  // Pagination
  const totalPages = Math.ceil(entries.length / entriesPerPage);
  const paginatedEntries = entries.slice(
    (currentPage - 1) * entriesPerPage,
    currentPage * entriesPerPage
  );

  const getStatusBadgeColor = (status: string) => {
    switch(status) {
      case 'present': return 'bg-green-100 text-green-800';
      case 'sick': return 'bg-amber-100 text-amber-800';
      case 'awol': return 'bg-red-100 text-red-800';
      case 'deserted': return 'bg-red-200 text-red-800';
      case 'leave_pass':
      case 'leave_maternity':
      case 'leave_paternity':
      case 'leave_study': return 'bg-blue-100 text-blue-800';
      case 'on_course': return 'bg-purple-100 text-purple-800';
      case 'on_suspension': return 'bg-gray-100 text-gray-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusLabel = (status: string) => {
    switch(status) {
      case 'present': return 'Present';
      case 'sick': return 'Sick';
      case 'awol': return 'AWOL';
      case 'deserted': return 'Deserted';
      case 'leave_pass': return 'On Leave (Pass)';
      case 'leave_maternity': return 'On Leave (Maternity)';
      case 'leave_paternity': return 'On Leave (Paternity)';
      case 'leave_study': return 'On Leave (Study)';
      case 'on_course': return 'On Course';
      case 'on_suspension': return 'On Suspension';
      default: return status;
    }
  };

  return (
    <div>
      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>File No.</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Rank</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Remarks</TableHead>
              {isEditable && <TableHead>Actions</TableHead>}
            </TableRow>
          </TableHeader>
          <TableBody>
            {paginatedEntries.map((entry) => (
              <TableRow key={entry.id}>
                <TableCell className="font-medium">{entry.employee.fileNumber}</TableCell>
                <TableCell>
                  <div>
                    <div className="font-medium">{entry.employee.fullName}</div>
                    <div className="text-sm text-gray-500">{entry.employee.supervisorType}</div>
                  </div>
                </TableCell>
                <TableCell>{entry.employee.rank}</TableCell>
                <TableCell>
                  <Badge className={getStatusBadgeColor(entry.status)}>
                    {getStatusLabel(entry.status)}
                  </Badge>
                </TableCell>
                <TableCell className="text-sm text-gray-600">{entry.remarks || '-'}</TableCell>
                {isEditable && (
                  <TableCell>
                    <div className="flex items-center space-x-2">
                      <Button variant="ghost" size="icon" onClick={() => handleEditClick(entry)}>
                        <Edit className="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="icon">
                        <MoreVertical className="h-4 w-4" />
                      </Button>
                    </div>
                  </TableCell>
                )}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {totalPages > 1 && (
        <div className="flex justify-between items-center mt-4">
          <div className="text-sm text-gray-500">
            Showing {(currentPage - 1) * entriesPerPage + 1} to {Math.min(currentPage * entriesPerPage, entries.length)} of {entries.length} entries
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

      {isEditable && availableEmployees.length > 0 && (
        <div className="mt-4">
          <Dialog>
            <DialogTrigger asChild>
              <Button>Add Personnel Status</Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Add Personnel Status</DialogTitle>
              </DialogHeader>
              <div className="py-4">
                <Select onValueChange={(value) => {
                  const employee = availableEmployees.find(emp => emp.id === parseInt(value));
                  if (employee) {
                    handleAddClick(employee);
                  }
                }}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select personnel" />
                  </SelectTrigger>
                  <SelectContent>
                    {availableEmployees.map(employee => (
                      <SelectItem key={employee.id} value={employee.id.toString()}>
                        {employee.fullName} ({employee.fileNumber})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </DialogContent>
          </Dialog>
        </div>
      )}

      {/* Edit Dialog */}
      <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {editingEntry?.id ? 'Edit Status' : 'Add Status'} - {editingEntry?.employee.fullName}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmitEdit}>
            <div className="space-y-4 py-4">
              <div className="space-y-2">
                <label className="text-sm font-medium">Status</label>
                <Select name="status" defaultValue={editingEntry?.status || 'present'}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="present">Present</SelectItem>
                    <SelectItem value="sick">Sick</SelectItem>
                    <SelectItem value="awol">AWOL</SelectItem>
                    <SelectItem value="deserted">Deserted</SelectItem>
                    <SelectItem value="leave_pass">On Leave (Pass)</SelectItem>
                    <SelectItem value="leave_maternity">On Leave (Maternity)</SelectItem>
                    <SelectItem value="leave_paternity">On Leave (Paternity)</SelectItem>
                    <SelectItem value="leave_study">On Leave (Study)</SelectItem>
                    <SelectItem value="on_course">On Course</SelectItem>
                    <SelectItem value="on_suspension">On Suspension</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <label className="text-sm font-medium">Remarks</label>
                <Textarea 
                  name="remarks"
                  placeholder="Add any additional remarks here..."
                  defaultValue={editingEntry?.remarks || ''}
                />
              </div>
            </div>
            <DialogFooter>
              <Button 
                type="button" 
                variant="outline" 
                onClick={() => setIsEditDialogOpen(false)}
              >
                Cancel
              </Button>
              <Button type="submit">
                {updateEntryMutation.isPending || addEntryMutation.isPending ? "Saving..." : "Save"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
