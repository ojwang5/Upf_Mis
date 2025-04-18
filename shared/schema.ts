import { pgTable, text, serial, integer, boolean, timestamp, date, pgEnum } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod";

// Enums
export const userRoleEnum = pgEnum('user_role', ['admin', 'branch_manager']);
export const branchEnum = pgEnum('branch', ['kampala', 'rwizi', 'nkyoga']);
export const genderEnum = pgEnum('gender', ['male', 'female']);
export const supervisorTypeEnum = pgEnum('supervisor_type', ['officer', 'nco', 'constable']);
export const statusEnum = pgEnum('status', ['present', 'sick', 'awol', 'deserted', 'leave_pass', 'leave_maternity', 'leave_paternity', 'leave_study', 'on_course', 'on_suspension']);

// Users table
export const users = pgTable("users", {
  id: serial("id").primaryKey(),
  username: text("username").notNull().unique(),
  password: text("password").notNull(),
  role: userRoleEnum("role").notNull(),
  branchAccess: branchEnum("branch_access"),
  fullName: text("full_name").notNull(),
});

// Branches table
export const branches = pgTable("branches", {
  id: serial("id").primaryKey(),
  name: text("name").notNull(),
  code: branchEnum("code").notNull().unique(),
  location: text("location").notNull(),
});

// Employees table
export const employees = pgTable("employees", {
  id: serial("id").primaryKey(),
  fileNumber: text("file_number").notNull().unique(),
  fullName: text("full_name").notNull(),
  gender: genderEnum("gender").notNull(),
  rank: text("rank").notNull(),
  instrument: text("instrument"),
  role: text("role").notNull(),
  supervisorType: supervisorTypeEnum("supervisor_type").notNull(),
  dateJoined: date("date_joined").notNull(),
  phone: text("phone"),
  email: text("email"),
  branchId: integer("branch_id").notNull(),
  supervisorId: integer("supervisor_id"),
});

// Daily Status table
export const dailyStatusReports = pgTable("daily_status_reports", {
  id: serial("id").primaryKey(),
  date: date("date").notNull(),
  branchId: integer("branch_id").notNull(),
  submittedBy: integer("submitted_by").notNull(),
  specialReport: text("special_report"),
  createdAt: timestamp("created_at").defaultNow().notNull(),
});

// Daily Status Entries table
export const dailyStatusEntries = pgTable("daily_status_entries", {
  id: serial("id").primaryKey(),
  reportId: integer("report_id").notNull(),
  employeeId: integer("employee_id").notNull(),
  status: statusEnum("status").notNull(),
  remarks: text("remarks"),
});

// Schemas for insertion
export const insertUserSchema = createInsertSchema(users).omit({ id: true });
export const insertBranchSchema = createInsertSchema(branches).omit({ id: true });
export const insertEmployeeSchema = createInsertSchema(employees).omit({ id: true });
export const insertDailyStatusReportSchema = createInsertSchema(dailyStatusReports).omit({ id: true, createdAt: true });
export const insertDailyStatusEntrySchema = createInsertSchema(dailyStatusEntries).omit({ id: true });

// Types for insertion
export type InsertUser = z.infer<typeof insertUserSchema>;
export type InsertBranch = z.infer<typeof insertBranchSchema>;
export type InsertEmployee = z.infer<typeof insertEmployeeSchema>;
export type InsertDailyStatusReport = z.infer<typeof insertDailyStatusReportSchema>;
export type InsertDailyStatusEntry = z.infer<typeof insertDailyStatusEntrySchema>;

// Types for selection
export type User = typeof users.$inferSelect;
export type Branch = typeof branches.$inferSelect;
export type Employee = typeof employees.$inferSelect;
export type DailyStatusReport = typeof dailyStatusReports.$inferSelect;
export type DailyStatusEntry = typeof dailyStatusEntries.$inferSelect;

// Extended types for API responses
export type EmployeeWithBranch = Employee & { branchName: string };
export type DailyStatusSummary = {
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

export type StatusReportWithEntries = DailyStatusReport & {
  entries: (DailyStatusEntry & { employee: Employee })[];
  summary: DailyStatusSummary;
};
