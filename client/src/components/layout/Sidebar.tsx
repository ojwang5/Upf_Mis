import { Link, useLocation } from 'wouter';
import { useAuth } from '@/hooks/use-auth';
import { 
  LucideLayoutDashboard, 
  Users, 
  ClipboardList, 
  BarChart, 
  Clock, 
  UserCog, 
  Settings
} from 'lucide-react';

interface SidebarProps {
  isOpen: boolean;
  closeSidebar: () => void;
  userRole: string;
  userName: string;
}

export default function Sidebar({ isOpen, closeSidebar, userRole, userName }: SidebarProps) {
  const [location] = useLocation();
  const { logoutMutation } = useAuth();
  
  const handleLogout = () => {
    logoutMutation.mutate();
  };
  
  // Handle clicks on mobile
  const handleLinkClick = () => {
    if (window.innerWidth < 1024) {
      closeSidebar();
    }
  };
  
  const navItems = [
    { 
      path: '/', 
      label: 'Dashboard', 
      icon: <LucideLayoutDashboard className="w-5 h-5 mr-3 text-blue-600" /> 
    },
    { 
      path: '/employees', 
      label: 'Employee List', 
      icon: <Users className="w-5 h-5 mr-3 text-blue-600" /> 
    },
    { 
      path: '/daily-status', 
      label: 'Daily Status', 
      icon: <ClipboardList className="w-5 h-5 mr-3 text-blue-600" /> 
    },
    { 
      path: '/reports', 
      label: 'Reports', 
      icon: <BarChart className="w-5 h-5 mr-3 text-blue-600" /> 
    },
    { 
      path: '/history', 
      label: 'History', 
      icon: <Clock className="w-5 h-5 mr-3 text-blue-600" /> 
    }
  ];
  
  const adminItems = [
    { 
      path: '/users', 
      label: 'User Management', 
      icon: <UserCog className="w-5 h-5 mr-3 text-blue-600" />,
      adminOnly: true 
    },
    { 
      path: '/settings', 
      label: 'Settings', 
      icon: <Settings className="w-5 h-5 mr-3 text-blue-600" />,
      adminOnly: true 
    }
  ];

  const sidebarClasses = `
    bg-white shadow-md w-64 flex-shrink-0 
    ${isOpen ? 'block' : 'hidden'} lg:block
    fixed lg:static inset-y-0 left-0 z-50
    transform ${isOpen ? 'translate-x-0' : '-translate-x-full'} 
    lg:translate-x-0 transition-transform duration-300 ease-in-out
    overflow-y-auto
  `;
  
  const isActive = (path: string) => {
    return location === path ? 
      'bg-blue-50 text-blue-700 rounded-r-full mr-2' : 
      'text-gray-700 hover:bg-gray-50 hover:text-blue-700 rounded-r-full mr-2';
  };

  return (
    <>
      {/* Overlay for mobile */}
      {isOpen && (
        <div 
          className="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
          onClick={closeSidebar}
        ></div>
      )}
      
      <aside className={sidebarClasses}>
        <nav className="py-4">
          <div className="px-4 mb-4">
            <div className="text-center p-2 bg-blue-700 rounded-lg text-white">
              <p className="text-sm opacity-80">Logged in as</p>
              <p className="font-medium">{getRoleName(userRole)}</p>
            </div>
          </div>
          
          <ul>
            {navItems.map((item) => (
              <li key={item.path} className="mb-1">
                <Link 
                  href={item.path} 
                  onClick={handleLinkClick}
                  className={`flex items-center px-4 py-3 ${isActive(item.path)}`}
                >
                  {item.icon}
                  {item.label}
                </Link>
              </li>
            ))}
            
            {(userRole === 'admin') && (
              <>
                <li className="mt-6 px-4">
                  <h3 className="text-xs font-medium uppercase text-gray-500 tracking-wider mb-2">Admin Tools</h3>
                </li>
                
                {adminItems.map((item) => (
                  <li key={item.path} className="mb-1">
                    <Link 
                      href={item.path} 
                      onClick={handleLinkClick}
                      className={`flex items-center px-4 py-3 ${isActive(item.path)}`}
                    >
                      {item.icon}
                      {item.label}
                    </Link>
                  </li>
                ))}
              </>
            )}
          </ul>
          
          <div className="px-4 mt-8">
            <button
              onClick={handleLogout}
              className="w-full flex items-center px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              Logout
            </button>
          </div>
        </nav>
      </aside>
    </>
  );
}

function getRoleName(role: string): string {
  switch(role) {
    case 'admin': return 'Administrator';
    case 'branch_manager': return 'Branch Manager';
    default: return role;
  }
}
