import { getApiBase } from '../lib/apiBase';

function apiUrl(path: string): string {
  const base = getApiBase().replace(/\/$/, '');
  const p = path.startsWith('/') ? path : `/${path}`;
  return `${base}${p}`;
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(apiUrl(path), {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(init.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
      ...(init.headers || {}),
    },
    ...init,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error((data as { message?: string }).message || 'Request failed');
  }
  return data as T;
}

export const api = {
  csrf: () => request<{ success: boolean; csrf_token: string }>('/auth/csrf.php'),
  me: () => request<{ success: boolean; authenticated: boolean; user: User | null }>('/auth/me.php'),
  login: (email: string, password: string) =>
    request<{ success: boolean; user: User; redirect: string }>('/auth/login.php', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),
  logout: () => request('/auth/logout.php', { method: 'POST' }),
  courses: () => request<{ success: boolean; data: Course[] }>('/courses/list.php'),
  course: (id: number) => request<{ success: boolean; data: Course }>(`/courses/get.php?id=${id}`),
  testimonials: (params = '') =>
    request<{ success: boolean; data: Testimonial[] }>(`/testimonials/list.php${params}`),
  paymentSettings: () => request<{ success: boolean; data: PaymentCredentials }>('/settings/payment.php'),
  calculateFee: (payload: Record<string, string | number>) => {
    const body = new URLSearchParams();
    Object.entries(payload).forEach(([k, v]) => body.set(k, String(v)));
    return request<{ success: boolean; data: FeeQuote }>('/registrations/calculate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
  },
  submitRegistration: async (form: FormData) => {
    const { csrf_token } = await api.csrf();
    form.set('csrf_token', csrf_token);
    form.set('dh_register', '1');
    return request<{ success: boolean; message: string }>('/registrations/submit.php', {
      method: 'POST',
      body: form,
    });
  },
  studentDashboard: () => request<{ success: boolean; data: StudentDashboard }>('/student/dashboard.php'),
  studentPayments: () => request<{ success: boolean; data: PaymentRow[] }>('/student/payments.php'),
  submitStudentPayment: async (mpesa_code: string, payer_reference: string) => {
    const { csrf_token } = await api.csrf();
    const body = new URLSearchParams({ csrf_token, mpesa_code, payer_reference });
    return request('/student/payments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
  },
  tutorDashboard: () => request<{ success: boolean; data: TutorDashboard }>('/tutor/dashboard.php'),
};

export interface User {
  id: number;
  email: string;
  username: string;
  role: string;
  role_id: number;
}

export interface Course {
  id: number;
  title: string;
  abbreviation: string;
  overview: string;
  description: string;
  duration_months: number;
  course_fee: number;
  cover_image: string;
  is_active: boolean;
  rating?: string;
}

export interface Testimonial {
  id: number;
  customer_name: string;
  role: string;
  service_tag: string;
  rating: number;
  testimonial_text: string;
  testimonial_type: 'text' | 'video';
  video_url: string;
  video_provider: string;
  avatar_url: string;
  thumbnail_url: string;
  is_featured: boolean;
}

export interface PaymentCredentials {
  bank: string;
  service: string;
  paybill: string;
  account_number: string;
  account_name: string;
}

export interface FeeQuote {
  registration_fee: number;
  installment_amount: number;
  total_due_now: number;
  installments: number[];
  course_fee: number;
}

export interface StudentDashboard {
  registration: {
    id: number;
    registration_no: string;
    course_title: string;
    study_mode: string;
    payment_plan: string;
    portal_locked: boolean;
    balance: number;
    total_paid: number;
    next_amount: number;
    installments: number[];
  };
  learning: Record<string, unknown>;
  has_pending_payment: boolean;
}

export interface PaymentRow {
  id: number;
  amount: number;
  status: string;
  mpesa_code?: string;
  created_at?: string;
}

export interface TutorDashboard {
  tutor: { id: number; full_name: string; courses: Course[] };
  stats: { courses: number; students: number; pending: number; students_progress?: unknown[] };
  pending_submissions: unknown[];
}
