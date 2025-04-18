import type { Express, Request, Response } from "express";
import { createServer, type Server } from "http";
import { storage } from "./storage";
import { setupAuth } from "./auth";
import { z } from "zod";
import { 
  insertEmployeeSchema, 
  insertDailyStatusReportSchema, 
  insertDailyStatusEntrySchema 
} from "@shared/schema";

export async function registerRoutes(app: Express): Promise<Server> {
  // Setup authentication routes
  setupAuth(app);

  // Get branches
  app.get("/api/branches", async (req, res) => {
    const branches = await storage.getBranches();
    res.json(branches);
  });

  // Get branch by ID
  app.get("/api/branches/:id", async (req, res) => {
    const id = parseInt(req.params.id);
    if (isNaN(id)) {
      return res.status(400).json({ message: "Invalid branch ID" });
    }

    const branch = await storage.getBranch(id);
    if (!branch) {
      return res.status(404).json({ message: "Branch not found" });
    }

    res.json(branch);
  });

  // Protected routes - require authentication
  app.get("/api/protected/employees", async (req, res) => {
    try {
      const user = req.user!;
      let employees;

      // If branch manager, only show employees from their branch
      if (user.role === 'branch_manager' && user.branchAccess) {
        const branch = await storage.getBranchByCode(user.branchAccess);
        if (branch) {
          employees = await storage.getEmployeesByBranch(branch.id);
        } else {
          employees = [];
        }
      } else {
        // Admin can see all employees
        employees = await storage.getEmployeesWithBranch();
      }

      res.json(employees);
    } catch (error) {
      console.error("Error fetching employees:", error);
      res.status(500).json({ message: "Error fetching employees" });
    }
  });

  // Get employee by ID
  app.get("/api/protected/employees/:id", async (req, res) => {
    try {
      const id = parseInt(req.params.id);
      if (isNaN(id)) {
        return res.status(400).json({ message: "Invalid employee ID" });
      }

      const employee = await storage.getEmployee(id);
      if (!employee) {
        return res.status(404).json({ message: "Employee not found" });
      }

      // Branch managers can only access employees in their branch
      if (req.user!.role === 'branch_manager' && req.user!.branchAccess) {
        const branch = await storage.getBranchByCode(req.user!.branchAccess);
        if (branch && employee.branchId !== branch.id) {
          return res.status(403).json({ message: "Access denied to this employee" });
        }
      }

      res.json(employee);
    } catch (error) {
      console.error("Error fetching employee:", error);
      res.status(500).json({ message: "Error fetching employee" });
    }
  });

  // Create employee
  app.post("/api/protected/employees", async (req, res) => {
    try {
      const employeeData = insertEmployeeSchema.parse(req.body);

      // Branch managers can only create employees in their branch
      if (req.user!.role === 'branch_manager' && req.user!.branchAccess) {
        const branch = await storage.getBranchByCode(req.user!.branchAccess);
        if (branch && employeeData.branchId !== branch.id) {
          return res.status(403).json({ message: "You can only create employees in your branch" });
        }
      }

      // Check if file number already exists
      const existingEmployee = await storage.getEmployeeByFileNumber(employeeData.fileNumber);
      if (existingEmployee) {
        return res.status(400).json({ message: "File number already exists" });
      }

      const employee = await storage.createEmployee(employeeData);
      res.status(201).json(employee);
    } catch (error) {
      if (error instanceof z.ZodError) {
        return res.status(400).json({ message: "Invalid employee data", errors: error.errors });
      }
      console.error("Error creating employee:", error);
      res.status(500).json({ message: "Error creating employee" });
    }
  });

  // Update employee
  app.patch("/api/protected/employees/:id", async (req, res) => {
    try {
      const id = parseInt(req.params.id);
      if (isNaN(id)) {
        return res.status(400).json({ message: "Invalid employee ID" });
      }

      const employee = await storage.getEmployee(id);
      if (!employee) {
        return res.status(404).json({ message: "Employee not found" });
      }

      // Branch managers can only update employees in their branch
      if (req.user!.role === 'branch_manager' && req.user!.branchAccess) {
        const branch = await storage.getBranchByCode(req.user!.branchAccess);
        if (branch && employee.branchId !== branch.id) {
          return res.status(403).json({ message: "Access denied to this employee" });
        }
      }

      const updatedEmployee = await storage.updateEmployee(id, req.body);
      res.json(updatedEmployee);
    } catch (error) {
      console.error("Error updating employee:", error);
      res.status(500).json({ message: "Error updating employee" });
    }
  });

  // Get daily status reports
  app.get("/api/protected/daily-status", async (req, res) => {
    try {
      const user = req.user!;
      let reports;

      // If branch manager, only show reports from their branch
      if (user.role === 'branch_manager' && user.branchAccess) {
        const branch = await storage.getBranchByCode(user.branchAccess);
        if (branch) {
          reports = await storage.getDailyStatusReportsByBranch(branch.id);
        } else {
          reports = [];
        }
      } else {
        // Admin can see all reports
        reports = await storage.getDailyStatusReports();
      }

      res.json(reports);
    } catch (error) {
      console.error("Error fetching daily status reports:", error);
      res.status(500).json({ message: "Error fetching daily status reports" });
    }
  });

  // Get daily status report with entries
  app.get("/api/protected/daily-status/:id", async (req, res) => {
    try {
      const id = parseInt(req.params.id);
      if (isNaN(id)) {
        return res.status(400).json({ message: "Invalid report ID" });
      }

      const report = await storage.getDailyStatusReportWithEntries(id);
      if (!report) {
        return res.status(404).json({ message: "Report not found" });
      }

      // Branch managers can only access reports from their branch
      if (req.user!.role === 'branch_manager' && req.user!.branchAccess) {
        const branch = await storage.getBranchByCode(req.user!.branchAccess);
        if (branch && report.branchId !== branch.id) {
          return res.status(403).json({ message: "Access denied to this report" });
        }
      }

      res.json(report);
    } catch (error) {
      console.error("Error fetching report:", error);
      res.status(500).json({ message: "Error fetching report" });
    }
  });

  // Create daily status report
  app.post("/api/protected/daily-status", async (req, res) => {
    try {
      const reportData = insertDailyStatusReportSchema.parse({
        ...req.body,
        submittedBy: req.user!.id
      });

      // Branch managers can only create reports for their branch
      if (req.user!.role === 'branch_manager' && req.user!.branchAccess) {
        const branch = await storage.getBranchByCode(req.user!.branchAccess);
        if (branch && reportData.branchId !== branch.id) {
          return res.status(403).json({ message: "You can only create reports for your branch" });
        }
      }

      // Check if report already exists for this date and branch
      const existingReport = await storage.getDailyStatusReportByDateAndBranch(
        new Date(reportData.date), 
        reportData.branchId
      );

      if (existingReport) {
        return res.status(400).json({ 
          message: "A report already exists for this date and branch",
          reportId: existingReport.id
        });
      }

      const report = await storage.createDailyStatusReport(reportData);
      res.status(201).json(report);
    } catch (error) {
      if (error instanceof z.ZodError) {
        return res.status(400).json({ message: "Invalid report data", errors: error.errors });
      }
      console.error("Error creating report:", error);
      res.status(500).json({ message: "Error creating report" });
    }
  });

  // Add entry to daily status report
  app.post("/api/protected/daily-status/:reportId/entries", async (req, res) => {
    try {
      const reportId = parseInt(req.params.reportId);
      if (isNaN(reportId)) {
        return res.status(400).json({ message: "Invalid report ID" });
      }

      const report = await storage.getDailyStatusReport(reportId);
      if (!report) {
        return res.status(404).json({ message: "Report not found" });
      }

      // Branch managers can only add entries to reports from their branch
      if (req.user!.role === 'branch_manager' && req.user!.branchAccess) {
        const branch = await storage.getBranchByCode(req.user!.branchAccess);
        if (branch && report.branchId !== branch.id) {
          return res.status(403).json({ message: "Access denied to this report" });
        }
      }

      const entryData = insertDailyStatusEntrySchema.parse({
        ...req.body,
        reportId
      });

      const entry = await storage.createDailyStatusEntry(entryData);
      res.status(201).json(entry);
    } catch (error) {
      if (error instanceof z.ZodError) {
        return res.status(400).json({ message: "Invalid entry data", errors: error.errors });
      }
      console.error("Error creating entry:", error);
      res.status(500).json({ message: "Error creating entry" });
    }
  });

  // Update entry in daily status report
  app.patch("/api/protected/daily-status/entries/:id", async (req, res) => {
    try {
      const id = parseInt(req.params.id);
      if (isNaN(id)) {
        return res.status(400).json({ message: "Invalid entry ID" });
      }

      const entries = await storage.getDailyStatusEntriesByReport(req.body.reportId);
      const entry = entries.find(e => e.id === id);
      
      if (!entry) {
        return res.status(404).json({ message: "Entry not found" });
      }

      const report = await storage.getDailyStatusReport(entry.reportId);
      if (!report) {
        return res.status(404).json({ message: "Report not found" });
      }

      // Branch managers can only update entries in reports from their branch
      if (req.user!.role === 'branch_manager' && req.user!.branchAccess) {
        const branch = await storage.getBranchByCode(req.user!.branchAccess);
        if (branch && report.branchId !== branch.id) {
          return res.status(403).json({ message: "Access denied to this report" });
        }
      }

      const updatedEntry = await storage.updateDailyStatusEntry(id, req.body);
      res.json(updatedEntry);
    } catch (error) {
      console.error("Error updating entry:", error);
      res.status(500).json({ message: "Error updating entry" });
    }
  });

  // Get daily status summary by branch
  app.get("/api/protected/summary", async (req, res) => {
    try {
      const reports = await storage.getDailyStatusReports();
      const branches = await storage.getBranches();
      
      const summaries = await Promise.all(
        branches.map(async (branch) => {
          // Get most recent report for this branch
          const branchReports = reports
            .filter(r => r.branchId === branch.id)
            .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
          
          if (branchReports.length === 0) {
            return {
              branch,
              summary: {
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
              }
            };
          }
          
          const latestReport = branchReports[0];
          const summary = await storage.getDailyStatusSummaryByReport(latestReport.id);
          
          return {
            branch,
            report: latestReport,
            summary
          };
        })
      );
      
      res.json(summaries);
    } catch (error) {
      console.error("Error fetching summaries:", error);
      res.status(500).json({ message: "Error fetching summaries" });
    }
  });
  
  const httpServer = createServer(app);
  return httpServer;
}
