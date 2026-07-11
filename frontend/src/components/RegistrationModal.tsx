import React, { useEffect, useRef, useState } from 'react';
import { api, type Course, type FeeQuote, type PaymentCredentials } from '../api/client';

interface RegistrationModalProps {
  isOpen: boolean;
  onClose: () => void;
  initialCourse?: string;
  initialCourseId?: number | null;
}

const RegistrationModal: React.FC<RegistrationModalProps> = ({
  isOpen,
  onClose,
  initialCourse = '',
  initialCourseId = null,
}) => {
  const [courses, setCourses] = useState<Course[]>([]);
  const [paymentInfo, setPaymentInfo] = useState<PaymentCredentials | null>(null);
  const [formData, setFormData] = useState({
    full_name: '',
    email: '',
    phone: '',
    course_id: '',
    study_mode: 'online',
    payment_plan: 'monthly',
    payment_reference: '',
  });
  const [idDocument, setIdDocument] = useState<File | null>(null);
  const [feeQuote, setFeeQuote] = useState<FeeQuote | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formStep, setFormStep] = useState(1);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [isRendered, setIsRendered] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const modalRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!isOpen) return;
    api.courses().then((r) => setCourses(r.data.filter((c) => c.is_active))).catch(() => setCourses([]));
    api.paymentSettings().then((r) => setPaymentInfo(r.data)).catch(() => setPaymentInfo(null));
  }, [isOpen]);

  useEffect(() => {
    if (isOpen) {
      triggerRef.current = document.activeElement as HTMLElement;
      setIsRendered(true);
      if (initialCourseId) {
        setFormData((prev) => ({ ...prev, course_id: String(initialCourseId) }));
      } else if (initialCourse && courses.length) {
        const match = courses.find((c) => c.title === initialCourse);
        if (match) setFormData((prev) => ({ ...prev, course_id: String(match.id) }));
      }
    } else {
      const timer = setTimeout(() => {
        setIsRendered(false);
        setFormData({
          full_name: '',
          email: '',
          phone: '',
          course_id: '',
          study_mode: 'online',
          payment_plan: 'monthly',
          payment_reference: '',
        });
        setIdDocument(null);
        setFeeQuote(null);
        setFormErrors({});
        setFormStep(1);
        setIsSubmitting(false);
        setServerError(null);
      }, 300);
      return () => clearTimeout(timer);
    }
  }, [isOpen, initialCourse, initialCourseId, courses]);

  useEffect(() => {
    if (!isOpen) return;
    const modalNode = modalRef.current;
    if (!modalNode) return;

    const focusableElements = modalNode.querySelectorAll<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    );
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];
    firstElement?.focus();

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Tab') {
        if (e.shiftKey) {
          if (document.activeElement === firstElement) {
            lastElement.focus();
            e.preventDefault();
          }
        } else if (document.activeElement === lastElement) {
          firstElement.focus();
          e.preventDefault();
        }
      } else if (e.key === 'Escape') {
        onClose();
      }
    };

    modalNode.addEventListener('keydown', handleKeyDown);
    return () => {
      modalNode.removeEventListener('keydown', handleKeyDown);
      triggerRef.current?.focus();
    };
  }, [isOpen, onClose, formStep]);

  useEffect(() => {
    if (!formData.course_id) {
      setFeeQuote(null);
      return;
    }
    api
      .calculateFee({
        course_id: formData.course_id,
        study_mode: formData.study_mode,
        payment_plan: formData.payment_plan,
      })
      .then((r) => setFeeQuote(r.data))
      .catch(() => setFeeQuote(null));
  }, [formData.course_id, formData.study_mode, formData.payment_plan]);

  const selectedCourse = courses.find((c) => String(c.id) === formData.course_id);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (formErrors[name]) setFormErrors((prev) => ({ ...prev, [name]: '' }));
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;
    setIdDocument(file);
    if (formErrors.id_document) setFormErrors((prev) => ({ ...prev, id_document: '' }));
  };

  const validateForm = () => {
    const errors: Record<string, string> = {};
    if (!formData.full_name.trim()) errors.full_name = 'Full name is required';
    if (!formData.email.trim()) errors.email = 'Email is required';
    else if (!/\S+@\S+\.\S+/.test(formData.email)) errors.email = 'Email is invalid';
    if (!formData.phone.trim()) errors.phone = 'Phone number is required';
    else if (!/^(07|01)\d{8}$/.test(formData.phone)) errors.phone = 'Enter a valid Kenyan number (e.g., 0712345678)';
    if (!formData.course_id) errors.course_id = 'Please select a course';
    if (!formData.payment_reference.trim()) errors.payment_reference = 'M-Pesa name or code is required';
    if (!idDocument) errors.id_document = 'Please upload your ID or result slip (PDF)';
    else if (idDocument.type !== 'application/pdf') errors.id_document = 'Only PDF files are accepted';
    else if (idDocument.size > 5 * 1024 * 1024) errors.id_document = 'File must be under 5 MB';

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateForm()) return;

    setIsSubmitting(true);
    setServerError(null);

    try {
      const body = new FormData();
      body.set('full_name', formData.full_name.trim());
      body.set('email', formData.email.trim());
      body.set('phone', formData.phone.trim());
      body.set('course_id', formData.course_id);
      body.set('study_mode', formData.study_mode);
      body.set('payment_plan', formData.payment_plan);
      body.set('payment_reference', formData.payment_reference.trim());
      if (idDocument) body.set('id_document', idDocument);

      await api.submitRegistration(body);
      setFormStep(2);
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'An unknown error occurred.';
      setServerError(errorMessage);
      setFormStep(4);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isRendered) return null;

  const formElementClasses = (hasError: boolean) =>
    `w-full px-4 py-3 rounded-xl transition input-3d ${hasError ? 'border border-red-500' : 'border border-transparent'}`;

  const renderContent = () => {
    switch (formStep) {
      case 1:
        return (
          <form onSubmit={handleSubmit} noValidate className="overflow-y-auto p-6 sm:p-8 space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label htmlFor="full_name" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Full Name
                </label>
                <input
                  type="text"
                  id="full_name"
                  name="full_name"
                  value={formData.full_name}
                  onChange={handleChange}
                  className={`${formElementClasses(!!formErrors.full_name)} bg-slate-50 dark:bg-slate-700`}
                  required
                />
                {formErrors.full_name && <p className="text-red-500 text-xs mt-1">{formErrors.full_name}</p>}
              </div>
              <div>
                <label htmlFor="phone" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Phone Number
                </label>
                <input
                  type="tel"
                  id="phone"
                  name="phone"
                  value={formData.phone}
                  onChange={handleChange}
                  placeholder="0712345678"
                  className={`${formElementClasses(!!formErrors.phone)} bg-slate-50 dark:bg-slate-700`}
                  required
                />
                {formErrors.phone && <p className="text-red-500 text-xs mt-1">{formErrors.phone}</p>}
              </div>
              <div className="md:col-span-2">
                <label htmlFor="email" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Email Address
                </label>
                <input
                  type="email"
                  id="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  className={`${formElementClasses(!!formErrors.email)} bg-slate-50 dark:bg-slate-700`}
                  required
                />
                {formErrors.email && <p className="text-red-500 text-xs mt-1">{formErrors.email}</p>}
              </div>
              <div className="md:col-span-2">
                <label htmlFor="course_id" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Choose Your Course
                </label>
                <select
                  id="course_id"
                  name="course_id"
                  value={formData.course_id}
                  onChange={handleChange}
                  className={`${formElementClasses(!!formErrors.course_id)} bg-white dark:bg-slate-700`}
                  required
                >
                  <option value="" disabled>
                    Select a course
                  </option>
                  {courses.map((course) => (
                    <option key={course.id} value={course.id}>
                      {course.title} — KES {course.course_fee.toLocaleString()}
                    </option>
                  ))}
                </select>
                {formErrors.course_id && <p className="text-red-500 text-xs mt-1">{formErrors.course_id}</p>}
              </div>
            </div>

            <div>
              <p className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Study Mode</p>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {[
                  { value: 'online', label: 'Online', note: 'KSh 1,000 registration fee' },
                  { value: 'physical', label: 'Physical', note: 'KSh 1,500 registration fee' },
                ].map((mode) => (
                  <label
                    key={mode.value}
                    className={`flex items-center gap-3 p-3 rounded-xl border cursor-pointer ${
                      formData.study_mode === mode.value
                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                        : 'border-slate-200 dark:border-slate-600'
                    }`}
                  >
                    <input
                      type="radio"
                      name="study_mode"
                      value={mode.value}
                      checked={formData.study_mode === mode.value}
                      onChange={handleChange}
                    />
                    <span>
                      <strong className="block">{mode.label}</strong>
                      <span className="text-xs text-slate-500">{mode.note}</span>
                    </span>
                  </label>
                ))}
              </div>
            </div>

            <div>
              <p className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Payment Plan</p>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {[
                  { value: 'monthly', label: 'Monthly' },
                  { value: '3months', label: '3-Month' },
                  { value: 'full', label: 'Full payment' },
                ].map((plan) => (
                  <label
                    key={plan.value}
                    className={`flex items-center gap-2 p-3 rounded-xl border cursor-pointer ${
                      formData.payment_plan === plan.value
                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                        : 'border-slate-200 dark:border-slate-600'
                    }`}
                  >
                    <input
                      type="radio"
                      name="payment_plan"
                      value={plan.value}
                      checked={formData.payment_plan === plan.value}
                      onChange={handleChange}
                    />
                    <strong>{plan.label}</strong>
                  </label>
                ))}
              </div>
            </div>

            {feeQuote && (
              <div className="bg-slate-100 dark:bg-slate-700/60 rounded-xl p-4 text-sm space-y-2">
                <div className="flex justify-between">
                  <span>Registration fee</span>
                  <strong>KES {feeQuote.registration_fee.toLocaleString()}</strong>
                </div>
                <div className="flex justify-between">
                  <span>First installment</span>
                  <strong>KES {feeQuote.installment_amount.toLocaleString()}</strong>
                </div>
                <div className="flex justify-between pt-2 border-t border-slate-300 dark:border-slate-600 text-base font-bold">
                  <span>Pay now</span>
                  <span className="gradient-text">KES {feeQuote.total_due_now.toLocaleString()}</span>
                </div>
              </div>
            )}

            {paymentInfo && (
              <div className="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-4 text-sm">
                <p className="font-semibold mb-1">Pay via M-Pesa (NCBA Paybill)</p>
                <p>
                  Paybill: <strong>{paymentInfo.paybill}</strong> · Account: <strong>{paymentInfo.account_number}</strong>
                </p>
                <p className="text-slate-600 dark:text-slate-400 mt-1">{paymentInfo.account_name}</p>
              </div>
            )}

            <div>
              <label htmlFor="payment_reference" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                M-Pesa Name / Code
              </label>
              <input
                type="text"
                id="payment_reference"
                name="payment_reference"
                value={formData.payment_reference}
                onChange={handleChange}
                placeholder="Jane Wanjiku or QGH7XXXX"
                className={`${formElementClasses(!!formErrors.payment_reference)} bg-slate-50 dark:bg-slate-700`}
                required
              />
              {formErrors.payment_reference && <p className="text-red-500 text-xs mt-1">{formErrors.payment_reference}</p>}
            </div>

            <div>
              <label htmlFor="id_document" className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                ID / Result Slip (PDF, max 5 MB)
              </label>
              <input
                type="file"
                id="id_document"
                name="id_document"
                accept="application/pdf"
                onChange={handleFileChange}
                className="w-full text-sm"
                required
              />
              {idDocument && <p className="text-xs text-slate-500 mt-1">{idDocument.name}</p>}
              {formErrors.id_document && <p className="text-red-500 text-xs mt-1">{formErrors.id_document}</p>}
            </div>

            <button
              type="submit"
              disabled={isSubmitting}
              className="w-full text-white font-bold px-6 py-4 rounded-xl hover:opacity-90 transition-opacity shadow-lg flex items-center justify-center disabled:opacity-50"
              style={{ background: 'var(--gradient)' }}
            >
              {isSubmitting ? 'Submitting…' : 'Submit Application'}
            </button>
          </form>
        );
      case 2:
        return (
          <div className="p-8 text-center">
            <svg className="w-20 h-20 text-green-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 className="text-2xl font-bold text-slate-900 dark:text-slate-100 mb-2">Application Received!</h3>
            <p className="text-slate-600 dark:text-slate-400 mb-6">
              Thank you, {formData.full_name}. Our team will verify your payment and contact you within 24 hours.
            </p>
            {selectedCourse && (
              <div className="bg-slate-100 dark:bg-slate-700 p-4 rounded-lg mb-6">
                <p className="font-semibold text-slate-800 dark:text-slate-200">{selectedCourse.title}</p>
                {feeQuote && <p className="text-2xl font-bold gradient-text">KES {feeQuote.total_due_now.toLocaleString()} paid</p>}
              </div>
            )}
            <button
              onClick={onClose}
              className="w-full text-white font-bold px-6 py-4 rounded-xl hover:opacity-90 transition-opacity shadow-lg"
              style={{ background: 'var(--gradient)' }}
            >
              Close
            </button>
          </div>
        );
      case 4:
        return (
          <div className="p-8 text-center">
            <svg className="w-20 h-20 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 className="text-2xl font-bold text-slate-900 dark:text-slate-100 mb-2">Something went wrong</h3>
            <p className="text-slate-600 dark:text-slate-400 mb-6">{serverError || "We couldn't process your registration."}</p>
            <button
              onClick={() => setFormStep(1)}
              className="w-full text-white font-bold px-6 py-4 rounded-xl hover:opacity-90 transition-opacity shadow-lg"
              style={{ background: 'var(--gradient)' }}
            >
              Try Again
            </button>
          </div>
        );
      default:
        return null;
    }
  };

  return (
    <div
      className={`fixed inset-0 z-50 transition-opacity duration-300 flex items-center justify-center p-4 ${
        isOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'
      }`}
      role="dialog"
      aria-modal="true"
      aria-labelledby="registration-modal-title"
    >
      <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
      <div
        ref={modalRef}
        className={`relative w-full max-w-2xl bg-white dark:bg-slate-800 rounded-3xl shadow-2xl flex flex-col max-h-[90vh] transition-all duration-300 ${
          isOpen ? 'scale-100 opacity-100' : 'scale-95 opacity-0'
        }`}
      >
        <div className="p-6 flex items-center justify-between border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
          <h2 id="registration-modal-title" className="text-2xl font-bold text-slate-900 dark:text-slate-100">
            {formStep === 1 ? 'Register for a Course' : 'Registration'}
          </h2>
          <button
            onClick={onClose}
            className="text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-full p-2"
            aria-label="Close registration form"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        {renderContent()}
      </div>
    </div>
  );
};

export default RegistrationModal;
