import {
  users, User, InsertUser,
  branches, Branch, InsertBranch,
  employees, Employee, InsertEmployee,
  dailyStatusReports, DailyStatusReport, InsertDailyStatusReport,
  dailyStatusEntries, DailyStatusEntry, InsertDailyStatusEntry,
  DailyStatusSummary, StatusReportWithEntries, EmployeeWithBranch
} from "@shared/schema";
import session from "express-session";
import createMemoryStore from "memorystore";

const MemoryStore = createMemoryStore(session);

export interface IStorage {
  // Session store
  sessionStore: session.Store;

  // User methods
  getUser(id: number): Promise<User | undefined>;
  getUserByUsername(username: string): Promise<User | undefined>;
  createUser(user: InsertUser): Promise<User>;
  getUsers(): Promise<User[]>;

  // Branch methods
  getBranch(id: number): Promise<Branch | undefined>;
  getBranchByCode(code: string): Promise<Branch | undefined>;
  createBranch(branch: InsertBranch): Promise<Branch>;
  getBranches(): Promise<Branch[]>;

  // Employee methods
  getEmployee(id: number): Promise<Employee | undefined>;
  getEmployeeByFileNumber(fileNumber: string): Promise<Employee | undefined>;
  createEmployee(employee: InsertEmployee): Promise<Employee>;
  updateEmployee(id: number, employee: Partial<InsertEmployee>): Promise<Employee | undefined>;
  getEmployeesByBranch(branchId: number): Promise<Employee[]>;
  getEmployeesWithBranch(): Promise<EmployeeWithBranch[]>;
  getEmployeesBySupervisorType(supervisorType: string): Promise<Employee[]>;

  // Daily Status methods
  createDailyStatusReport(report: InsertDailyStatusReport): Promise<DailyStatusReport>;
  getDailyStatusReport(id: number): Promise<DailyStatusReport | undefined>;
  getDailyStatusReportByDateAndBranch(date: Date, branchId: number): Promise<DailyStatusReport | undefined>;
  getDailyStatusReports(): Promise<DailyStatusReport[]>;
  getDailyStatusReportsByBranch(branchId: number): Promise<DailyStatusReport[]>;
  
  // Daily Status Entries methods
  createDailyStatusEntry(entry: InsertDailyStatusEntry): Promise<DailyStatusEntry>;
  getDailyStatusEntriesByReport(reportId: number): Promise<DailyStatusEntry[]>;
  updateDailyStatusEntry(id: number, entry: Partial<InsertDailyStatusEntry>): Promise<DailyStatusEntry | undefined>;
  
  // Complex queries
  getDailyStatusReportWithEntries(reportId: number): Promise<StatusReportWithEntries | undefined>;
  getDailyStatusSummaryByReport(reportId: number): Promise<DailyStatusSummary>;
}

export class MemStorage implements IStorage {
  sessionStore: session.Store;
  private _users: Map<number, User>;
  private _branches: Map<number, Branch>;
  private _employees: Map<number, Employee>;
  private _dailyStatusReports: Map<number, DailyStatusReport>;
  private _dailyStatusEntries: Map<number, DailyStatusEntry>;
  private userIdCounter: number;
  private branchIdCounter: number;
  private employeeIdCounter: number;
  private reportIdCounter: number;
  private entryIdCounter: number;

  constructor() {
    this.sessionStore = new MemoryStore({
      checkPeriod: 86400000 // 24 hours
    });
    this._users = new Map();
    this._branches = new Map();
    this._employees = new Map();
    this._dailyStatusReports = new Map();
    this._dailyStatusEntries = new Map();
    this.userIdCounter = 1;
    this.branchIdCounter = 1;
    this.employeeIdCounter = 1;
    this.reportIdCounter = 1;
    this.entryIdCounter = 1;

    // Initialize with default data
    this.initializeData();
  }

  private initializeData() {
    // Create default branches
    const kampala = this.createBranch({
      name: "Kampala Headquarters",
      code: "kampala",
      location: "Kampala"
    });

    const rwizi = this.createBranch({
      name: "Rwizi Mbarara",
      code: "rwizi",
      location: "Mbarara"
    });

    const nkyoga = this.createBranch({
      name: "N.Kyoga Lira",
      code: "nkyoga",
      location: "Lira"
    });

    // Create default users
    this.createUser({
      username: "admin",
      password: "admin123",  // would be hashed in production
      role: "admin",
      fullName: "Administrator"
    });

    this.createUser({
      username: "kmgr",
      password: "kmgr123",  // would be hashed in production
      role: "branch_manager",
      branchAccess: "kampala",
      fullName: "Kampala Manager"
    });

    this.createUser({
      username: "rmgr",
      password: "rmgr123",  // would be hashed in production
      role: "branch_manager",
      branchAccess: "rwizi",
      fullName: "Rwizi Manager"
    });

    this.createUser({
      username: "nmgr",
      password: "nmgr123",  // would be hashed in production
      role: "branch_manager",
      branchAccess: "nkyoga",
      fullName: "N.Kyoga Manager"
    });
  }

  // User methods
  async getUser(id: number): Promise<User | undefined> {
    return this._users.get(id);
  }

  async getUserByUsername(username: string): Promise<User | undefined> {
    return Array.from(this._users.values()).find(
      (user) => user.username === username
    );
  }

  async createUser(insertUser: InsertUser): Promise<User> {
    const id = this.userIdCounter++;
    const user: User = { ...insertUser, id };
    this._users.set(id, user);
    return user;
  }

  async getUsers(): Promise<User[]> {
    return Array.from(this._users.values());
  }

  // Branch methods
  async getBranch(id: number): Promise<Branch | undefined> {
    return this._branches.get(id);
  }

  async getBranchByCode(code: string): Promise<Branch | undefined> {
    return Array.from(this._branches.values()).find(
      (branch) => branch.code === code
    );
  }

  async createBranch(insertBranch: InsertBranch): Promise<Branch> {
    const id = this.branchIdCounter++;
    const branch: Branch = { ...insertBranch, id };
    this._branches.set(id, branch);
    return branch;
  }

  async getBranches(): Promise<Branch[]> {
    return Array.from(this._branches.values());
  }

  // Employee methods
  async getEmployee(id: number): Promise<Employee | undefined> {
    return this._employees.get(id);
  }

  async getEmployeeByFileNumber(fileNumber: string): Promise<Employee | undefined> {
    return Array.from(this._employees.values()).find(
      (employee) => employee.fileNumber === fileNumber
    );
  }

  async createEmployee(insertEmployee: InsertEmployee): Promise<Employee> {
    const id = this.employeeIdCounter++;
    const employee: Employee = { ...insertEmployee, id };
    this._employees.set(id, employee);
    return employee;
  }

  async updateEmployee(id: number, employeeUpdate: Partial<InsertEmployee>): Promise<Employee | undefined> {
    const employee = this._employees.get(id);
    if (!employee) return undefined;

    const updatedEmployee = { ...employee, ...employeeUpdate };
    this._employees.set(id, updatedEmployee);
    return updatedEmployee;
  }

  async getEmployeesByBranch(branchId: number): Promise<Employee[]> {
    return Array.from(this._employees.values()).filter(
      (employee) => employee.branchId === branchId
    );
  }

  async getEmployeesWithBranch(): Promise<EmployeeWithBranch[]> {
    return Promise.all(
      Array.from(this._employees.values()).map(async (employee) => {
        const branch = await this.getBranch(employee.branchId);
        return {
          ...employee,
          branchName: branch?.name || "Unknown Branch"
        };
      })
    );
  }

  async getEmployeesBySupervisorType(supervisorType: string): Promise<Employee[]> {
    return Array.from(this._employees.values()).filter(
      (employee) => employee.supervisorType === supervisorType
    );
  }

  // Daily Status methods
  async createDailyStatusReport(insertReport: InsertDailyStatusReport): Promise<DailyStatusReport> {
    const id = this.reportIdCounter++;
    const report: DailyStatusReport = { 
      ...insertReport, 
      id,
      createdAt: new Date()
    };
    this._dailyStatusReports.set(id, report);
    return report;
  }

  async getDailyStatusReport(id: number): Promise<DailyStatusReport | undefined> {
    return this._dailyStatusReports.get(id);
  }

  async getDailyStatusReportByDateAndBranch(date: Date, branchId: number): Promise<DailyStatusReport | undefined> {
    const dateString = date.toISOString().split('T')[0];
    return Array.from(this._dailyStatusReports.values()).find(
      (report) => {
        const reportDate = new Date(report.date).toISOString().split('T')[0];
        return reportDate === dateString && report.branchId === branchId;
      }
    );
  }

  async getDailyStatusReports(): Promise<DailyStatusReport[]> {
    return Array.from(this._dailyStatusReports.values());
  }

  async getDailyStatusReportsByBranch(branchId: number): Promise<DailyStatusReport[]> {
    return Array.from(this._dailyStatusReports.values()).filter(
      (report) => report.branchId === branchId
    );
  }

  // Daily Status Entries methods
  async createDailyStatusEntry(insertEntry: InsertDailyStatusEntry): Promise<DailyStatusEntry> {
    const id = this.entryIdCounter++;
    const entry: DailyStatusEntry = { ...insertEntry, id };
    this._dailyStatusEntries.set(id, entry);
    return entry;
  }

  async getDailyStatusEntriesByReport(reportId: number): Promise<DailyStatusEntry[]> {
    return Array.from(this._dailyStatusEntries.values()).filter(
      (entry) => entry.reportId === reportId
    );
  }

  async updateDailyStatusEntry(id: number, entryUpdate: Partial<InsertDailyStatusEntry>): Promise<DailyStatusEntry | undefined> {
    const entry = this._dailyStatusEntries.get(id);
    if (!entry) return undefined;

    const updatedEntry = { ...entry, ...entryUpdate };
    this._dailyStatusEntries.set(id, updatedEntry);
    return updatedEntry;
  }

  // Complex queries
  async getDailyStatusReportWithEntries(reportId: number): Promise<StatusReportWithEntries | undefined> {
    const report = await this.getDailyStatusReport(reportId);
    if (!report) return undefined;

    const entries = await this.getDailyStatusEntriesByReport(reportId);
    const entriesWithEmployee = await Promise.all(
      entries.map(async (entry) => {
        const employee = await this.getEmployee(entry.employeeId);
        return {
          ...entry,
          employee: employee!
        };
      })
    );

    const summary = await this.getDailyStatusSummaryByReport(reportId);

    return {
      ...report,
      entries: entriesWithEmployee,
      summary
    };
  }

  async getDailyStatusSummaryByReport(reportId: number): Promise<DailyStatusSummary> {
    const entries = await this.getDailyStatusEntriesByReport(reportId);
    const report = await this.getDailyStatusReport(reportId);
    
    if (!report) {
      return {
        total: 0,
        present: 0,
        sick: 0,
        awol: 0,
        deserted: 0,
        onLeave: 0,
        onCourse: 0,
        onSuspension: 0,
        maleCount: 0,
        femaleCount: 0
      };
    }

    const employees = await this.getEmployeesByBranch(report.branchId);
    
    // Count by status
    const present = entries.filter(entry => entry.status === 'present').length;
    const sick = entries.filter(entry => entry.status === 'sick').length;
    const awol = entries.filter(entry => entry.status === 'awol').length;
    const deserted = entries.filter(entry => entry.status === 'deserted').length;
    const onLeave = entries.filter(entry => 
      entry.status === 'leave_pass' || 
      entry.status === 'leave_maternity' || 
      entry.status === 'leave_paternity' || 
      entry.status === 'leave_study'
    ).length;
    const onCourse = entries.filter(entry => entry.status === 'on_course').length;
    const onSuspension = entries.filter(entry => entry.status === 'on_suspension').length;

    // Count by gender
    const employeeIdsInEntries = entries.map(entry => entry.employeeId);
    const employeesInEntries = employees.filter(emp => employeeIdsInEntries.includes(emp.id));
    
    const maleCount = employeesInEntries.filter(emp => emp.gender === 'male').length;
    const femaleCount = employeesInEntries.filter(emp => emp.gender === 'female').length;

    return {
      total: entries.length,
      present,
      sick,
      awol,
      deserted,
      onLeave,
      onCourse,
      onSuspension,
      maleCount,
      femaleCount
    };
  }
}

import { DatabaseStorage } from './database-storage';

// Export database storage instead of memory storage
export const storage = new DatabaseStorage();
