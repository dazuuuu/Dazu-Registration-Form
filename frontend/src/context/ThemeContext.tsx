import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';

export type ViewType =
  | 'homepage'
  | 'courses'
  | 'services'
  | 'prompt-center'
  | 'testimonials'
  | 'ask-dazu'
  | 'newsletter'
  | 'course-detail';

type Theme = 'light' | 'dark';

interface ThemeContextType {
  theme: Theme;
  toggleTheme: () => void;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export const ThemeProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [theme, setTheme] = useState<Theme>(() => {
    const stored = localStorage.getItem('dazu-theme') as Theme | null;
    return stored || 'light';
  });

  useEffect(() => {
    const root = document.documentElement;
    root.classList.remove('light', 'dark');
    root.classList.add(theme);
    localStorage.setItem('dazu-theme', theme);
  }, [theme]);

  const value = useMemo(
    () => ({
      theme,
      toggleTheme: () => setTheme((t) => (t === 'light' ? 'dark' : 'light')),
    }),
    [theme],
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
};

export const useTheme = (): ThemeContextType => {
  const ctx = useContext(ThemeContext);
  if (!ctx) throw new Error('useTheme must be used within ThemeProvider');
  return ctx;
};

export function pathToView(pathname: string): ViewType {
  if (pathname.startsWith('/courses/')) return 'course-detail';
  if (pathname.startsWith('/courses')) return 'courses';
  if (pathname.startsWith('/services')) return 'services';
  if (pathname.startsWith('/prompt-center')) return 'prompt-center';
  if (pathname.startsWith('/testimonials')) return 'testimonials';
  if (pathname.startsWith('/ask-dazu')) return 'ask-dazu';
  if (pathname.startsWith('/newsletter')) return 'newsletter';
  return 'homepage';
}

export function viewToPath(view: ViewType): string {
  switch (view) {
    case 'courses':
    case 'course-detail':
      return '/courses';
    case 'services':
      return '/services';
    case 'prompt-center':
      return '/prompt-center';
    case 'testimonials':
      return '/testimonials';
    case 'ask-dazu':
      return '/ask-dazu';
    case 'newsletter':
      return '/newsletter';
    default:
      return '/';
  }
}
