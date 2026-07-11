import React, { createContext, useContext } from 'react';
import { Link as RRLink, useLocation, useNavigate } from 'react-router-dom';

interface RouterContextType {
  path: string;
  navigate: (to: string) => void;
}

const RouterContext = createContext<RouterContextType | undefined>(undefined);

export function RouterProvider({ children }: { children: React.ReactNode }) {
  const navigate = useNavigate();
  const location = useLocation();

  const value: RouterContextType = {
    path: location.pathname,
    navigate: (to: string) => {
      const target = to.startsWith('/') ? to : `/${to}`;
      navigate(target);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
  };

  return <RouterContext.Provider value={value}>{children}</RouterContext.Provider>;
}

export function useRouter() {
  const context = useContext(RouterContext);
  if (!context) {
    throw new Error('useRouter must be used within a RouterProvider');
  }
  return context;
}

interface LinkProps {
  to: string;
  children: React.ReactNode;
  className?: string;
  id?: string;
}

export function Link({ to, children, className, id }: LinkProps) {
  const { path } = useRouter();
  const isActive = path === to || (to !== '/' && path.startsWith(to));

  return (
    <RRLink
      to={to}
      id={id}
      className={`${className || ''} ${isActive ? 'text-green-600 font-semibold' : ''}`}
    >
      {children}
    </RRLink>
  );
}
