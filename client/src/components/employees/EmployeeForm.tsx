import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation } from '@tanstack/react-query';
import { apiRequest, queryClient } from '@/lib/queryClient';
import { useToast } from '@/hooks/use-toast';
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Loader2 } from 'lucide-react';

// Define schema for employee form
const employeeSchema = z.object({
  fileNumber: z.string().min(1, "File number is required"),
  fullName: z.string().min(1, "Full name is required"),
  gender: z.enum(["male", "female"]),
  rank: z.string().min(1, "Rank is required"),
  instrument: z.string().optional(),
  role: z.string().min(1, "Role is required"),
  supervisorType: z.enum(["officer", "nco", "constable"]),
  dateJoined: z.string().min(1, "Date joined is required"),
  phone: z.string().optional(),
  email: z.string().email("Invalid email").optional().or(z.literal('')),
  branchId: z.number({
    required_error: "Branch is required",
    invalid_type_error: "Branch must be a number",
  }),
  supervisorId: z.number().optional(),
});

type EmployeeFormValues = z.infer<typeof employeeSchema>;

interface Branch {
  id: number;
  name: string;
  code: string;
  location: string;
}

interface EmployeeFormProps {
  employee?: any;
  branches: Branch[];
  onSuccess: () => void;
  defaultBranchId?: number;
}

export default function EmployeeForm({ 
  employee, 
  branches, 
  onSuccess,
  defaultBranchId
}: EmployeeFormProps) {
  const { toast } = useToast();
  const isEditing = !!employee;
  
  // Set default values based on whether we're editing or creating
  const defaultValues: Partial<EmployeeFormValues> = {
    fileNumber: employee?.fileNumber || '',
    fullName: employee?.fullName || '',
    gender: employee?.gender || 'male',
    rank: employee?.rank || '',
    instrument: employee?.instrument || '',
    role: employee?.role || '',
    supervisorType: employee?.supervisorType || 'constable',
    dateJoined: employee?.dateJoined ? new Date(employee.dateJoined).toISOString().split('T')[0] : '',
    phone: employee?.phone || '',
    email: employee?.email || '',
    branchId: employee?.branchId || defaultBranchId || (branches[0]?.id || 0),
    supervisorId: employee?.supervisorId || undefined,
  };

  const form = useForm<EmployeeFormValues>({
    resolver: zodResolver(employeeSchema),
    defaultValues,
  });

  // Create employee mutation
  const createMutation = useMutation({
    mutationFn: async (data: EmployeeFormValues) => {
      return await apiRequest('POST', '/api/protected/employees', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['/api/protected/employees'] });
      toast({
        title: "Employee created",
        description: "The employee has been successfully added to the system.",
      });
      onSuccess();
    },
    onError: (error) => {
      toast({
        title: "Failed to create employee",
        description: error.message,
        variant: "destructive",
      });
    }
  });

  // Update employee mutation
  const updateMutation = useMutation({
    mutationFn: async (data: EmployeeFormValues) => {
      return await apiRequest('PATCH', `/api/protected/employees/${employee.id}`, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['/api/protected/employees'] });
      toast({
        title: "Employee updated",
        description: "The employee information has been successfully updated.",
      });
      onSuccess();
    },
    onError: (error) => {
      toast({
        title: "Failed to update employee",
        description: error.message,
        variant: "destructive",
      });
    }
  });

  function onSubmit(values: EmployeeFormValues) {
    if (isEditing) {
      updateMutation.mutate(values);
    } else {
      createMutation.mutate(values);
    }
  }

  const isPending = createMutation.isPending || updateMutation.isPending;

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="fileNumber"
            render={({ field }) => (
              <FormItem>
                <FormLabel>File Number</FormLabel>
                <FormControl>
                  <Input placeholder="e.g., KHQ-001" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          
          <FormField
            control={form.control}
            name="fullName"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Full Name</FormLabel>
                <FormControl>
                  <Input placeholder="Full name" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          
          <FormField
            control={form.control}
            name="gender"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Gender</FormLabel>
                <Select onValueChange={field.onChange} defaultValue={field.value}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Select gender" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="male">Male</SelectItem>
                    <SelectItem value="female">Female</SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />
          
          <FormField
            control={form.control}
            name="rank"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Rank/Position</FormLabel>
                <FormControl>
                  <Input placeholder="e.g., Captain, Sergeant" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          
          <FormField
            control={form.control}
            name="instrument"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Instrument Played</FormLabel>
                <FormControl>
                  <Input placeholder="e.g., Trumpet, Drums" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          
          <FormField
            control={form.control}
            name="role"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Role</FormLabel>
                <FormControl>
                  <Input placeholder="e.g., Musician, Drum Major" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          
          <FormField
            control={form.control}
            name="supervisorType"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Supervisor Type</FormLabel>
                <Select onValueChange={field.onChange} defaultValue={field.value}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Select type" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="officer">Officer</SelectItem>
                    <SelectItem value="nco">NCO</SelectItem>
                    <SelectItem value="constable">Constable</SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />
          
          <FormField
            control={form.control}
            name="dateJoined"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Date of Joining</FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          
          <FormField
            control={form.control}
            name="phone"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Phone Number</FormLabel>
                <FormControl>
                  <Input placeholder="Phone number" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          
          <FormField
            control={form.control}
            name="email"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Email</FormLabel>
                <FormControl>
                  <Input placeholder="Email address" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          
          <FormField
            control={form.control}
            name="branchId"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Branch</FormLabel>
                <Select 
                  onValueChange={(value) => field.onChange(parseInt(value))} 
                  value={field.value.toString()}
                >
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Select branch" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {branches.map(branch => (
                      <SelectItem key={branch.id} value={branch.id.toString()}>
                        {branch.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>
        
        <div className="flex justify-end space-x-2 pt-4">
          <Button type="button" variant="outline" onClick={onSuccess}>
            Cancel
          </Button>
          <Button type="submit" disabled={isPending}>
            {isPending ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                {isEditing ? "Updating..." : "Creating..."}
              </>
            ) : (
              isEditing ? "Update Employee" : "Create Employee"
            )}
          </Button>
        </div>
      </form>
    </Form>
  );
}
