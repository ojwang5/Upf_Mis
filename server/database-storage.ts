import {
  users, User, InsertUser,
  branches, Branch, InsertBranch,
  employees, Employee, InsertEmployee,
  dailyStatusReports, DailyStatusReport, InsertDailyStatusReport,
  dailyStatusEntries, DailyStatusEntry, InsertDailyStatusEntry,
  DailyStatusSummary, StatusReportWithEntries, EmployeeWithBranch
} from "@shared/schema";
import session from "express-session";
import connectPg from "connect-pg-simple";
import { db, pool } from "./db";
import { eq, and, count, sql, desc } from "drizzle-orm";
import { IStorage } from "./storage";

const PostgresSessionStore = connectPg(session);

export class DatabaseStorage implements IStorage {
  sessionStore: session.Store;

  constructor() {
    this.sessionStore = new PostgresSessionStore({ 
      pool, 
      createTableIfMissing: true 
    });
  }

  async getUser(id: number): Promise<User | undefined> {
    const [user] = await db.select().from(users).where(eq(users.id, id));
    return user;
  }

  async getUserByUsername(username: string): Promise<User | undefined> {
    const [user] = await db.select().from(users).where(eq(users.username, username));
    return user;
  }

  async createUser(insertUser: InsertUser): Promise<User> {
    const [user] = await db.insert(users).values(insertUser).returning();
    return user;
  }

  async getUsers(): Promise<User[]> {
    return await db.select().from(users);
  }

  async getBranch(id: number): Promise<Branch | undefined> {
    const [branch] = await db.select().from(branches).where(eq(branches.id, id));
    return branch;
  }

  async getBranchByCode(code: string): Promise<Branch | undefined> {
    const [branch] = await db.select().from(branches).where(sql`${branches.code} = ${code}`);
    return branch;
  }

  async createBranch(insertBranch: InsertBranch): Promise<Branch> {
    const [branch] = await db.insert(branches).values(insertBranch).returning();
    return branch;
  }

  async getBranches(): Promise<Branch[]> {
    return await db.select().from(branches);
  }

  async getEmployee(id: number): Promise<Employee | undefined> {
    const [employee] = await db.select().from(employees).where(eq(employees.id, id));
    return employee;
  }

  async getEmployeeByFileNumber(fileNumber: string): Promise<Employee | undefined> {
    const [employee] = await db.select().from(employees).where(eq(employees.fileNumber, fileNumber));
    return employee;
  }

  async createEmployee(insertEmployee: InsertEmployee): Promise<Employee> {
    const [employee] = await db.insert(employees).values(insertEmployee).returning();
    return employee;
  }

  async updateEmployee(id: number, employeeUpdate: Partial<InsertEmployee>): Promise<Employee | undefined> {
    const [updated] = await db
      .update(employees)
      .set(employeeUpdate)
      .where(eq(employees.id, id))
      .returning();
    return updated;
  }

  async getEmployeesByBranch(branchId: number): Promise<Employee[]> {
    return await db.select().from(employees).where(eq(employees.branchId, branchId));
  }

  async getEmployeesWithBranch(): Promise<EmployeeWithBranch[]> {
    // Fetch employees and branches separately
    const employeeList = await db.select().from(employees);
    const branchList = await db.select().from(branches);
    
    // Create a map of branch id to branch name for quick lookup
    const branchMap = new Map<number, string>();
    branchList.forEach(branch => {
      branchMap.set(branch.id, branch.name);
    });
    
    // Map employees with branch name
    return employeeList.map(employee => ({
      ...employee,
      branchName: branchMap.get(employee.branchId) || 'Unknown Branch'
    }));
  }

  async getEmployeesBySupervisorType(supervisorType: string): Promise<Employee[]> {
    return await db.select().from(employees).where(sql`${employees.supervisorType} = ${supervisorType}`);
  }

  async createDailyStatusReport(insertReport: InsertDailyStatusReport): Promise<DailyStatusReport> {
    const [report] = await db
      .insert(dailyStatusReports)
      .values({
        ...insertReport,
        createdAt: new Date()
      })
      .returning();
    return report;
  }

  async getDailyStatusReport(id: number): Promise<DailyStatusReport | undefined> {
    const [report] = await db.select().from(dailyStatusReports).where(eq(dailyStatusReports.id, id));
    return report;
  }

  async getDailyStatusReportByDateAndBranch(date: Date, branchId: number): Promise<DailyStatusReport | undefined> {
    const dateString = date.toISOString().split('T')[0];
    const [report] = await db
      .select()
      .from(dailyStatusReports)
      .where(
        and(
          eq(dailyStatusReports.branchId, branchId),
          eq(sql`DATE(${dailyStatusReports.date})`, dateString)
        )
      );
    return report;
  }

  async getDailyStatusReports(): Promise<DailyStatusReport[]> {
    return await db.select().from(dailyStatusReports).orderBy(desc(dailyStatusReports.createdAt));
  }

  async getDailyStatusReportsByBranch(branchId: number): Promise<DailyStatusReport[]> {
    return await db
      .select()
      .from(dailyStatusReports)
      .where(eq(dailyStatusReports.branchId, branchId))
      .orderBy(desc(dailyStatusReports.createdAt));
  }

  async createDailyStatusEntry(insertEntry: InsertDailyStatusEntry): Promise<DailyStatusEntry> {
    const [entry] = await db.insert(dailyStatusEntries).values(insertEntry).returning();
    return entry;
  }

  async getDailyStatusEntriesByReport(reportId: number): Promise<DailyStatusEntry[]> {
    return await db.select().from(dailyStatusEntries).where(eq(dailyStatusEntries.reportId, reportId));
  }

  async updateDailyStatusEntry(id: number, entryUpdate: Partial<InsertDailyStatusEntry>): Promise<DailyStatusEntry | undefined> {
    const [updated] = await db
      .update(dailyStatusEntries)
      .set(entryUpdate)
      .where(eq(dailyStatusEntries.id, id))
      .returning();
    return updated;
  }

  async getDailyStatusReportWithEntries(reportId: number): Promise<StatusReportWithEntries | undefined> {
    const report = await this.getDailyStatusReport(reportId);
    if (!report) return undefined;
    
    // Get entries for this report
    const entries = await this.getDailyStatusEntriesByReport(reportId);
    
    // Get all employees involved in this report
    const employeeIds = [...new Set(entries.map(entry => entry.employeeId))];
    const employeesList = await Promise.all(
      employeeIds.map(id => this.getEmployee(id))
    );
    
    // Create a map of employee ID to employee data for quick lookup
    const employeeMap = new Map<number, Employee>();
    employeesList.forEach(employee => {
      if (employee) {
        employeeMap.set(employee.id, employee);
      }
    });
    
    // Map entries with employee data
    const entriesWithEmployees = entries.map(entry => {
      const employee = employeeMap.get(entry.employeeId);
      // Ensure we never have null employees
      if (!employee) {
        throw new Error(`Employee with ID ${entry.employeeId} not found`);
      }
      return {
        ...entry,
        employee
      };
    });
    
    const summary = await this.getDailyStatusSummaryByReport(reportId);
    
    return {
      ...report,
      entries: entriesWithEmployees,
      summary
    };
  }

  async getDailyStatusSummaryByReport(reportId: number): Promise<DailyStatusSummary> {
    // Get the count of all entries for this report
    const [{ value: total }] = await db
      .select({ value: count() })
      .from(dailyStatusEntries)
      .where(eq(dailyStatusEntries.reportId, reportId));
    
    // Get present count
    const [{ value: present }] = await db
      .select({ value: count() })
      .from(dailyStatusEntries)
      .where(and(
        eq(dailyStatusEntries.reportId, reportId),
        eq(dailyStatusEntries.status, 'present')
      ));
    
    // Get sick count
    const [{ value: sick }] = await db
      .select({ value: count() })
      .from(dailyStatusEntries)
      .where(and(
        eq(dailyStatusEntries.reportId, reportId),
        eq(dailyStatusEntries.status, 'sick')
      ));
    
    // Get AWOL count
    const [{ value: awol }] = await db
      .select({ value: count() })
      .from(dailyStatusEntries)
      .where(and(
        eq(dailyStatusEntries.reportId, reportId),
        eq(dailyStatusEntries.status, 'awol')
      ));
    
    // Get deserted count
    const [{ value: deserted }] = await db
      .select({ value: count() })
      .from(dailyStatusEntries)
      .where(and(
        eq(dailyStatusEntries.reportId, reportId),
        eq(dailyStatusEntries.status, 'deserted')
      ));
    
    // Get on leave count (combined all leave types)
    const [{ value: onLeave }] = await db
      .select({ value: count() })
      .from(dailyStatusEntries)
      .where(and(
        eq(dailyStatusEntries.reportId, reportId),
        sql`${dailyStatusEntries.status} IN ('leave_pass', 'leave_maternity', 'leave_paternity', 'leave_study')`
      ));
    
    // Get on course count
    const [{ value: onCourse }] = await db
      .select({ value: count() })
      .from(dailyStatusEntries)
      .where(and(
        eq(dailyStatusEntries.reportId, reportId),
        eq(dailyStatusEntries.status, 'on_course')
      ));
    
    // Get on suspension count
    const [{ value: onSuspension }] = await db
      .select({ value: count() })
      .from(dailyStatusEntries)
      .where(and(
        eq(dailyStatusEntries.reportId, reportId),
        eq(dailyStatusEntries.status, 'on_suspension')
      ));
    
    // Count male employees
    const [{ value: maleCount }] = await db
      .select({ value: count() })
      .from(dailyStatusEntries)
      .innerJoin(employees, eq(dailyStatusEntries.employeeId, employees.id))
      .where(and(
        eq(dailyStatusEntries.reportId, reportId),
        eq(employees.gender, 'male')
      ));
    
    // Count female employees
    const [{ value: femaleCount }] = await db
      .select({ value: count() })
      .from(dailyStatusEntries)
      .innerJoin(employees, eq(dailyStatusEntries.employeeId, employees.id))
      .where(and(
        eq(dailyStatusEntries.reportId, reportId),
        eq(employees.gender, 'female')
      ));
    
    return {
      total: Number(total) || 0,
      present: Number(present) || 0,
      sick: Number(sick) || 0,
      awol: Number(awol) || 0,
      deserted: Number(deserted) || 0,
      onLeave: Number(onLeave) || 0,
      onCourse: Number(onCourse) || 0,
      onSuspension: Number(onSuspension) || 0,
      maleCount: Number(maleCount) || 0,
      femaleCount: Number(femaleCount) || 0
    };
  }
}