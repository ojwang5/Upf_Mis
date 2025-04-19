import { Switch, Route } from "wouter";
import { queryClient } from "./lib/queryClient";
import { QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import NotFound from "@/pages/not-found";
import AuthPage from "@/pages/auth-page";
import DashboardPage from "@/pages/dashboard-page";
import EmployeesPage from "@/pages/employees-page";
import DailyStatusPage from "@/pages/daily-status-page";
import ReportsPage from "@/pages/reports-page";
import HistoryPage from "@/pages/history-page";
import { ProtectedRoute } from "./lib/protected-route";
import { AuthProvider } from "./hooks/use-auth";
import AppLayout from "./components/layout/AppLayout";

// Wrap each page component with the layout
const WrappedDashboard = () => (
  <AppLayout>
    <DashboardPage />
  </AppLayout>
);

const WrappedEmployees = () => (
  <AppLayout>
    <EmployeesPage />
  </AppLayout>
);

const WrappedDailyStatus = () => (
  <AppLayout>
    <DailyStatusPage />
  </AppLayout>
);

const WrappedReports = () => (
  <AppLayout>
    <ReportsPage />
  </AppLayout>
);

const WrappedHistory = () => (
  <AppLayout>
    <HistoryPage />
  </AppLayout>
);

function Router() {
  return (
    <Switch>
      <Route path="/auth" component={AuthPage} />
      <ProtectedRoute path="/" component={WrappedDashboard} />
      <ProtectedRoute path="/employees" component={WrappedEmployees} />
      <ProtectedRoute path="/daily-status" component={WrappedDailyStatus} />
      <ProtectedRoute path="/reports" component={WrappedReports} />
      <ProtectedRoute path="/history" component={WrappedHistory} />
      <Route component={NotFound} />
    </Switch>
  );
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <TooltipProvider>
          <Toaster />
          <Router />
        </TooltipProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

export default App;
