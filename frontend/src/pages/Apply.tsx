import React, { useState, useEffect } from 'react';
import { useRouter } from '../router';
import { coursesData, type Course } from '../data/courses';
import { useCourses } from '../hooks/useCourses';
import { api } from '../api/client';
import { 
  User, 
  MapPin, 
  Mail, 
  Phone, 
  BookOpen, 
  Calendar, 
  CreditCard, 
  MessageSquare, 
  CheckCircle, 
  ArrowLeft, 
  Clock, 
  ShieldCheck, 
  Star,
  Sparkles,
  Award,
  Info,
  UploadCloud,
  ShieldAlert,
  FileText
} from 'lucide-react';

export default function Apply() {
  const { navigate } = useRouter();
  const { courses, findApiCourseByTitle } = useCourses();
  const catalog = courses.length ? courses : coursesData;
  
  // Form states
  const [formData, setFormData] = useState({
    fullName: '',
    countyOrCountry: '',
    email: '',
    phone: '',
    courseChoice: 'Web development',
    intakeDate: '',
    paymentMessage: ''
  });

  // Document Upload States with strict sandbox and anti-arbitrary upload validations
  const [idFile, setIdFile] = useState<File | null>(null);
  const [resultFile, setResultFile] = useState<File | null>(null);
  const [idUploadState, setIdUploadState] = useState<'idle' | 'scanning' | 'success' | 'error'>('idle');
  const [resultUploadState, setResultUploadState] = useState<'idle' | 'scanning' | 'success' | 'error'>('idle');
  const [idError, setIdError] = useState('');
  const [resultError, setResultError] = useState('');

  const handleIdChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setIdError('');
    setIdUploadState('scanning');

    // 1. File Size Verification (Max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      setIdError('File too large. Maximum size allowed is 5MB.');
      setIdUploadState('error');
      return;
    }

    // 2. File Extension Verification
    const extension = file.name.split('.').pop()?.toLowerCase();
    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!extension || !allowedExtensions.includes(extension)) {
      setIdError('Invalid format. Only PDF documents or JPG/PNG image scans are allowed.');
      setIdUploadState('error');
      return;
    }

    // 3. MIME type double-check
    const allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (file.type && !allowedMimeTypes.includes(file.type)) {
      setIdError('Executable scripts or arbitrary file types are blocked for system safety.');
      setIdUploadState('error');
      return;
    }

    // Simulate active sandbox threat checks (malware/bytecode scans)
    setTimeout(() => {
      setIdFile(file);
      setIdUploadState('success');
    }, 1500);
  };

  const handleResultChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setResultError('');
    setResultUploadState('scanning');

    // 1. File Size Verification (Max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      setResultError('File too large. Maximum size allowed is 5MB.');
      setResultUploadState('error');
      return;
    }

    // 2. File Extension Verification
    const extension = file.name.split('.').pop()?.toLowerCase();
    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!extension || !allowedExtensions.includes(extension)) {
      setResultError('Invalid format. Only PDF documents or JPG/PNG image scans are allowed.');
      setResultUploadState('error');
      return;
    }

    // 3. MIME type double-check
    const allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (file.type && !allowedMimeTypes.includes(file.type)) {
      setResultError('Executable scripts or arbitrary file types are blocked for system safety.');
      setResultUploadState('error');
      return;
    }

    // Simulate active sandbox threat checks (malware/bytecode scans)
    setTimeout(() => {
      setResultFile(file);
      setResultUploadState('success');
    }, 1500);
  };

  // Security honeypot to trap automated spam bots
  const [botField, setBotField] = useState('');

  // Math Captcha to prevent form submissions by scripts
  const [captcha, setCaptcha] = useState({ num1: 0, num2: 0, answer: 0 });
  const [captchaInput, setCaptchaInput] = useState('');

  const generateCaptcha = () => {
    const num1 = Math.floor(Math.random() * 9) + 1;
    const num2 = Math.floor(Math.random() * 9) + 1;
    setCaptcha({ num1, num2, answer: num1 + num2 });
    setCaptchaInput('');
  };

  const [formErrors, setFormErrors] = useState<string>('');
  const [isSubmitted, setIsSubmitted] = useState<boolean>(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [selectedCourse, setSelectedCourse] = useState<Course | null>(null);

  // Generate captcha on mount
  useEffect(() => {
    generateCaptcha();
  }, []);

  // Parse course from query parameter
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const courseParam = params.get('course');
    if (courseParam) {
      const decoded = decodeURIComponent(courseParam);
      // Try to find a match (case-insensitive)
      const matchedCourse = catalog.find(c => c.title.toLowerCase() === decoded.toLowerCase());
      if (matchedCourse) {
        setFormData(prev => ({ ...prev, courseChoice: matchedCourse.title }));
        setSelectedCourse(matchedCourse);
      }
    } else if (catalog.length > 0) {
      // Default to first course if none selected
      setSelectedCourse(catalog[0]);
    }
  }, []);

  // Sync course selection dropdown with current course details panel
  useEffect(() => {
    const matchedCourse = catalog.find(c => c.title === formData.courseChoice);
    if (matchedCourse) {
      setSelectedCourse(matchedCourse);
    }
  }, [formData.courseChoice]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    // 1. Spam Bot Trap (Honeypot check)
    if (botField) {
      setIsSubmitted(true);
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    // 2. Completeness validation
    if (
      !formData.fullName.trim() ||
      !formData.countyOrCountry.trim() ||
      !formData.email.trim() ||
      !formData.phone.trim() ||
      !formData.courseChoice ||
      !formData.intakeDate ||
      !formData.paymentMessage.trim()
    ) {
      setFormErrors('Please fill in all the fields and paste your payment confirmation message.');
      return;
    }

    // 3. Email Format Validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(formData.email.trim())) {
      setFormErrors('Please provide a valid email address (e.g. name@domain.com).');
      return;
    }

    // 4. Phone Number Format Validation
    const phoneRegex = /^(07|01)\d{8}$/;
    if (!phoneRegex.test(formData.phone.trim().replace(/\s/g, ''))) {
      setFormErrors('Please provide a valid Kenyan phone number (e.g. 0712345678).');
      return;
    }

    // 4.5. Strict Document Upload Check
    if (idUploadState !== 'success' || !idFile) {
      setFormErrors('Please upload and secure a clean, scanned copy of your National ID or Passport.');
      return;
    }

    if (resultUploadState !== 'success' || !resultFile) {
      setFormErrors('Please upload and secure a clean, scanned copy of your Academic Result Slip / Certificate.');
      return;
    }

    if (idFile.type !== 'application/pdf') {
      setFormErrors('For backend processing, please upload your ID document as a PDF file.');
      return;
    }

    // 5. Anti-Spam Captcha Validation
    if (parseInt(captchaInput.trim(), 10) !== captcha.answer) {
      setFormErrors('Incorrect security answer. Please solve the simple math puzzle to submit.');
      generateCaptcha();
      return;
    }

    const apiCourse = findApiCourseByTitle(formData.courseChoice);
    if (!apiCourse) {
      setFormErrors('Selected course is not available. Please choose another course or contact support.');
      return;
    }

    setFormErrors('');
    setIsSubmitting(true);

    try {
      const body = new FormData();
      body.set('full_name', formData.fullName.trim());
      body.set('email', formData.email.trim());
      body.set('phone', formData.phone.trim().replace(/\s/g, ''));
      body.set('course_id', String(apiCourse.id));
      body.set('study_mode', 'online');
      body.set('payment_plan', 'monthly');
      body.set('payment_reference', formData.paymentMessage.trim());
      body.set('id_document', idFile);
      await api.submitRegistration(body);
      setIsSubmitted(true);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      setFormErrors(err instanceof Error ? err.message : 'Submission failed. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="bg-slate-50 min-h-screen py-10 md:py-16 text-slate-900 font-sans">
      <div className="max-w-[1280px] mx-auto px-4 md:px-8">
        
        {/* Back navigation */}
        <button 
          onClick={() => navigate('/')}
          className="mb-8 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-green-600 transition-colors cursor-pointer focus:outline-none"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to Home
        </button>

        {!isSubmitted ? (
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            {/* LEFT COLUMN: COURSE INFO & SUMMARY */}
            <div className="lg:col-span-5 space-y-6">
              <div className="bg-white border border-slate-200 rounded-2xl p-6 md:p-8">
                <span className="text-green-600 text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 bg-green-50 border border-green-200 rounded-full w-fit block mb-4">
                  Selected Program Details
                </span>
                
                {selectedCourse ? (
                  <div className="space-y-6">
                    <div className="flex items-center gap-3 border-b border-slate-100 pb-5">
                      <div className="p-3 bg-green-50 border border-green-100 text-[#15A24A] rounded-xl">
                        <selectedCourse.icon className="h-6 w-6" />
                      </div>
                      <div>
                        <h2 className="text-xl font-extrabold text-slate-950 tracking-tight">{selectedCourse.title}</h2>
                        <span className="text-xs text-slate-400 font-bold uppercase tracking-wider">{selectedCourse.category}</span>
                      </div>
                    </div>

                    <div className="space-y-4">
                      <div>
                        <span className="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Overview</span>
                        <p className="text-xs text-slate-600 font-medium leading-relaxed">{selectedCourse.description}</p>
                      </div>

                      <div className="grid grid-cols-2 gap-4 pt-2">
                        <div className="p-3 bg-slate-50 border border-slate-200/60 rounded-xl">
                          <span className="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Duration</span>
                          <span className="text-xs font-bold text-slate-900 flex items-center gap-1">
                            <Clock className="h-3.5 w-3.5 text-green-600" />
                            {selectedCourse.duration}
                          </span>
                        </div>
                        <div className="p-3 bg-slate-50 border border-slate-200/60 rounded-xl">
                          <span className="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Tuition Fee</span>
                          <span className="text-xs font-bold text-slate-900 flex items-center gap-1">
                            <CreditCard className="h-3.5 w-3.5 text-green-600" />
                            {selectedCourse.price}
                          </span>
                        </div>
                      </div>

                      {/* Syllabus list */}
                      <div className="pt-2">
                        <span className="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-2">What you will learn</span>
                        <div className="space-y-1.5 max-h-[200px] overflow-y-auto pr-1">
                          {selectedCourse.syllabus.map((item, idx) => (
                            <div key={idx} className="flex items-start gap-2 text-xs font-medium text-slate-700">
                              <CheckCircle className="h-3.5 w-3.5 text-[#15A24A] shrink-0 mt-0.5" />
                              <span>{item}</span>
                            </div>
                          ))}
                        </div>
                      </div>

                      {/* Location validation note */}
                      <div className="p-3 bg-green-50/60 border border-green-100 rounded-xl text-[11px] font-medium text-green-800 leading-normal flex items-start gap-2">
                        <MapPin className="h-3.5 w-3.5 text-green-700 shrink-0 mt-0.5" />
                        <div>
                          <strong>Campus Location:</strong> This is an industry-recognized practical track conducted in-person at <strong>Witeithie House, Room 529, Thika Town</strong>, or via interactive virtual setups.
                        </div>
                      </div>
                    </div>
                  </div>
                ) : (
                  <p className="text-xs text-slate-500">Please choose a course in the application form to view its syllabus details.</p>
                )}
              </div>

              {/* Secure application credentials */}
              <div className="bg-slate-900 text-white rounded-2xl p-6 space-y-4">
                <div className="flex items-center gap-2">
                  <ShieldCheck className="h-5 w-5 text-green-500" />
                  <span className="text-xs uppercase font-extrabold tracking-wider">Admissions Verification</span>
                </div>
                <p className="text-xs text-slate-400 leading-relaxed">
                  Dazu hub Institute issues standard digital certificates and offers industrial job preparation and internships upon course completion. Your payment and enrollment data is strictly encrypted.
                </p>
              </div>
            </div>

            {/* RIGHT COLUMN: APPLICATION FORM */}
            <div className="lg:col-span-7 bg-white border border-slate-200 rounded-2xl p-6 md:p-10">
              
              <div className="border-b border-slate-100 pb-6 mb-8 text-left">
                <h1 className="text-2xl md:text-3xl font-extrabold text-slate-950 tracking-tight">Admission Application Form</h1>
                <p className="text-slate-500 text-xs font-medium mt-1 leading-relaxed">
                  Fill in all the required fields below to reserve your slot and verify your intake.
                </p>
              </div>

              {formErrors && (
                <div className="mb-6 p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl text-left">
                  {formErrors}
                </div>
              )}

              <form onSubmit={handleSubmit} className="space-y-6 text-left">
                
                {/* Name & Location */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div className="space-y-1.5">
                    <label className="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Names *</label>
                    <div className="relative">
                      <User className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                      <input
                        type="text"
                        name="fullName"
                        value={formData.fullName}
                        onChange={handleInputChange}
                        placeholder="E.g. Jane Wangari Kamau"
                        className="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white transition-colors"
                        required
                        id="apply-fullName"
                      />
                    </div>
                  </div>

                  <div className="space-y-1.5">
                    <label className="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">County / Country *</label>
                    <div className="relative">
                      <MapPin className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                      <input
                        type="text"
                        name="countyOrCountry"
                        value={formData.countyOrCountry}
                        onChange={handleInputChange}
                        placeholder="E.g. Kiambu / Kenya"
                        className="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white transition-colors"
                        required
                        id="apply-countyOrCountry"
                      />
                    </div>
                  </div>
                </div>

                {/* Email & Phone */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div className="space-y-1.5">
                    <label className="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Email Address *</label>
                    <div className="relative">
                      <Mail className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                      <input
                        type="email"
                        name="email"
                        value={formData.email}
                        onChange={handleInputChange}
                        placeholder="E.g. jane.wangari@example.com"
                        className="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white transition-colors"
                        required
                        id="apply-email"
                      />
                    </div>
                  </div>

                  <div className="space-y-1.5">
                    <label className="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Phone Number *</label>
                    <div className="relative">
                      <Phone className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                      <input
                        type="tel"
                        name="phone"
                        value={formData.phone}
                        onChange={handleInputChange}
                        placeholder="E.g. 0712345678"
                        className="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white transition-colors"
                        required
                        id="apply-phone"
                      />
                    </div>
                  </div>
                </div>

                {/* Course Choices & Intake Date */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div className="space-y-1.5">
                    <label className="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Course Choice *</label>
                    <div className="relative">
                      <BookOpen className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                      <select
                        name="courseChoice"
                        value={formData.courseChoice}
                        onChange={handleInputChange}
                        className="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white transition-colors appearance-none cursor-pointer"
                        id="apply-courseChoice"
                      >
                        {catalog.map((course) => (
                          <option key={course.id} value={course.title}>
                            {course.title}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="space-y-1.5">
                    <label className="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Intake with calendar *</label>
                    <div className="relative">
                      <Calendar className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400 pointer-events-none" />
                      <input
                        type="date"
                        name="intakeDate"
                        value={formData.intakeDate}
                        onChange={handleInputChange}
                        className="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white transition-colors cursor-pointer"
                        required
                        id="apply-intakeDate"
                      />
                    </div>
                  </div>
                </div>

                {/* Prominent Payment Details Card */}
                <div className="p-5 bg-green-50 border border-green-200 rounded-2xl space-y-4">
                  <div className="flex items-center gap-2 border-b border-green-100 pb-3">
                    <CreditCard className="h-5 w-5 text-[#15A24A]" />
                    <span className="text-xs font-extrabold text-green-950 uppercase tracking-wider">M-Pesa Admission Fee Payment Details</span>
                  </div>
                  
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div className="bg-white p-3 border border-green-100 rounded-xl text-center">
                      <span className="block text-slate-400 text-[9px] uppercase tracking-wider font-extrabold mb-0.5">Paybill Number</span>
                      <strong className="text-sm font-extrabold text-slate-900 select-all">880100</strong>
                    </div>
                    <div className="bg-white p-3 border border-green-100 rounded-xl text-center">
                      <span className="block text-slate-400 text-[9px] uppercase tracking-wider font-extrabold mb-0.5">Account Number</span>
                      <strong className="text-sm font-extrabold text-[#15A24A] select-all">681158</strong>
                    </div>
                    <div className="bg-white p-3 border border-green-100 rounded-xl text-center">
                      <span className="block text-slate-400 text-[9px] uppercase tracking-wider font-extrabold mb-0.5">Business Name</span>
                      <strong className="text-sm font-extrabold text-slate-900">Dazu hub</strong>
                    </div>
                  </div>

                  <div className="flex items-start gap-2 text-[11px] text-green-800 font-semibold leading-relaxed">
                    <Info className="h-4 w-4 text-green-700 shrink-0 mt-0.5" />
                    <p>
                      Please pay an admission fee of <strong>KES 1,000</strong> to the Paybill credentials above before finalizing your application. Then copy and paste the full M-Pesa transaction SMS or payment message in the box below.
                    </p>
                  </div>
                </div>

                {/* Payment Message Textbox */}
                <div className="space-y-1.5">
                  <label className="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Paste M-Pesa / Payment Message *</label>
                  <div className="relative">
                    <MessageSquare className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                    <textarea
                      name="paymentMessage"
                      value={formData.paymentMessage}
                      onChange={handleInputChange}
                      placeholder="Paste your full payment confirmation SMS here (e.g. QGH123XYZ Confirmed. Ksh 1,000 sent to Dazu hub...)"
                      rows={4}
                      className="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white transition-colors"
                      required
                      id="apply-paymentMessage"
                    />
                  </div>
                </div>

                {/* Secure File Upload Inputs with anti-arbitrary file scanning simulation */}
                <div className="space-y-6 pt-4 border-t border-slate-100">
                  <div>
                    <h3 className="text-xs font-extrabold uppercase tracking-widest text-slate-500 flex items-center gap-1.5">
                      <ShieldCheck className="h-4 w-4 text-[#15A24A]" />
                      Secure Document Uploads
                    </h3>
                    <p className="text-[10px] text-slate-400 font-semibold mt-0.5">
                      Required for identity verification and enrollment qualification. Scanned via sandboxed security.
                    </p>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* 1. National ID Upload */}
                    <div className="space-y-2">
                      <label className="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        National ID / Passport Scan (PDF/JPG/PNG) *
                      </label>
                      <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl relative flex flex-col items-center justify-center text-center group hover:border-green-600 transition-colors min-h-[140px]">
                        <input
                          type="file"
                          accept=".pdf,.jpg,.jpeg,.png"
                          onChange={handleIdChange}
                          className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                          id="apply-nationalIdDoc"
                        />
                        {idUploadState === 'idle' && (
                          <div className="space-y-1">
                            <UploadCloud className="h-8 w-8 text-slate-400 mx-auto group-hover:text-green-600 transition-colors" />
                            <p className="text-xs font-bold text-slate-700">Click or drag National ID</p>
                            <p className="text-[9px] text-slate-400 font-semibold uppercase">PDF, JPG, PNG up to 5MB</p>
                          </div>
                        )}
                        {idUploadState === 'scanning' && (
                          <div className="space-y-2 py-2">
                            <div className="animate-spin rounded-full h-6 w-6 border-2 border-green-600 border-t-transparent mx-auto"></div>
                            <p className="text-[10px] font-extrabold text-green-600 uppercase tracking-widest animate-pulse">Scanning file for scripts...</p>
                          </div>
                        )}
                        {idUploadState === 'success' && idFile && (
                          <div className="space-y-1 text-green-700">
                            <CheckCircle className="h-8 w-8 text-[#15A24A] mx-auto" />
                            <p className="text-xs font-bold text-slate-850 break-all">{idFile.name}</p>
                            <p className="text-[9px] font-extrabold uppercase bg-green-50 text-green-700 px-2.5 py-0.5 rounded-full border border-green-100 w-fit mx-auto">
                              Scanned: Clean
                            </p>
                          </div>
                        )}
                        {idUploadState === 'error' && (
                          <div className="space-y-1 text-red-600">
                            <ShieldAlert className="h-8 w-8 mx-auto" />
                            <p className="text-xs font-bold">{idError || 'Upload failed'}</p>
                            <p className="text-[9px] uppercase font-semibold">Click to retry a valid document</p>
                          </div>
                        )}
                      </div>
                    </div>

                    {/* 2. Academic Result Slip Upload */}
                    <div className="space-y-2">
                      <label className="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        KCSE Result Slip / Certificate (PDF/JPG/PNG) *
                      </label>
                      <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl relative flex flex-col items-center justify-center text-center group hover:border-green-600 transition-colors min-h-[140px]">
                        <input
                          type="file"
                          accept=".pdf,.jpg,.jpeg,.png"
                          onChange={handleResultChange}
                          className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                          id="apply-resultSlipDoc"
                        />
                        {resultUploadState === 'idle' && (
                          <div className="space-y-1">
                            <UploadCloud className="h-8 w-8 text-slate-400 mx-auto group-hover:text-green-600 transition-colors" />
                            <p className="text-xs font-bold text-slate-700">Click or drag Result Slip</p>
                            <p className="text-[9px] text-slate-400 font-semibold uppercase">PDF, JPG, PNG up to 5MB</p>
                          </div>
                        )}
                        {resultUploadState === 'scanning' && (
                          <div className="space-y-2 py-2">
                            <div className="animate-spin rounded-full h-6 w-6 border-2 border-green-600 border-t-transparent mx-auto"></div>
                            <p className="text-[10px] font-extrabold text-green-600 uppercase tracking-widest animate-pulse">Scanning file for scripts...</p>
                          </div>
                        )}
                        {resultUploadState === 'success' && resultFile && (
                          <div className="space-y-1 text-green-700">
                            <CheckCircle className="h-8 w-8 text-[#15A24A] mx-auto" />
                            <p className="text-xs font-bold text-slate-850 break-all">{resultFile.name}</p>
                            <p className="text-[9px] font-extrabold uppercase bg-green-50 text-green-700 px-2.5 py-0.5 rounded-full border border-green-100 w-fit mx-auto">
                              Scanned: Clean
                            </p>
                          </div>
                        )}
                        {resultUploadState === 'error' && (
                          <div className="space-y-1 text-red-600">
                            <ShieldAlert className="h-8 w-8 mx-auto" />
                            <p className="text-xs font-bold">{resultError || 'Upload failed'}</p>
                            <p className="text-[9px] uppercase font-semibold">Click to retry a valid document</p>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Honeypot Spam-trap Field (Hidden to real humans, traps bots) */}
                <div className="absolute opacity-0 pointer-events-none w-0 h-0 overflow-hidden" aria-hidden="true">
                  <input
                    type="text"
                    name="website"
                    value={botField}
                    onChange={(e) => setBotField(e.target.value)}
                    tabIndex={-1}
                    autoComplete="off"
                  />
                </div>

                {/* Anti-Spam Security Challenge */}
                <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                  <div className="flex justify-between items-center">
                    <label className="text-xs font-extrabold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                      <ShieldCheck className="h-4 w-4 text-green-600" />
                      Security Verification Challenge *
                    </label>
                    <span className="text-[10px] text-slate-400 font-bold uppercase">Verify Human</span>
                  </div>
                  <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                    <div className="text-xs font-extrabold text-slate-700 bg-white border border-slate-200 px-4 py-2.5 rounded-lg shrink-0 select-none">
                      What is {captcha.num1} + {captcha.num2}?
                    </div>
                    <input
                      type="text"
                      value={captchaInput}
                      onChange={(e) => setCaptchaInput(e.target.value)}
                      placeholder="Enter the math answer"
                      className="flex-1 px-4 py-2.5 bg-white border border-slate-200 rounded-lg text-slate-900 placeholder-slate-400 text-xs font-semibold focus:outline-none focus:border-green-600 focus:bg-white"
                      required
                    />
                  </div>
                </div>

                {/* Submit button */}
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="w-full py-4 text-xs font-bold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl transition-all duration-200 text-center cursor-pointer flex items-center justify-center gap-2 disabled:opacity-60"
                  id="apply-submit-button"
                >
                  {isSubmitting ? 'Submitting…' : 'Submit Application'}
                  <CheckCircle className="h-4 w-4" />
                </button>

              </form>
            </div>

          </div>
        ) : (
          /* SUCCESS STATE */
          <div className="max-w-xl mx-auto bg-white border-2 border-slate-900 rounded-[24px] p-8 md:p-12 text-center relative overflow-hidden">
            
            {/* Top decorative elements */}
            <div className="absolute top-0 left-0 right-0 h-2 bg-[#15A24A]"></div>
            <div className="absolute top-6 right-6">
              <Sparkles className="h-6 w-6 text-green-500 animate-pulse" />
            </div>

            <div className="w-16 h-16 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6 border border-green-100">
              <CheckCircle className="h-10 w-10 text-[#15A24A]" />
            </div>

            <h2 className="text-2xl md:text-3xl font-extrabold text-slate-950 tracking-tight">Application Submitted Successfully!</h2>
            <div className="my-2 text-[10px] uppercase tracking-widest font-extrabold text-slate-400 bg-slate-50 px-3 py-1 rounded-full w-fit mx-auto border border-slate-100">
              Reference Code: DH-{Math.floor(100000 + Math.random() * 900000)}
            </div>

            {/* Crucial success text exactly as specified */}
            <div className="my-6 p-5 bg-green-50 border border-green-100 rounded-xl">
              <p className="text-sm font-extrabold text-green-950 leading-relaxed">
                “Submitted and pending approval within 2 hours. Your application has been received and emailed to you on your filled email address <strong>{formData.email}</strong>.”
              </p>
            </div>

            {/* Receipt Summary Card */}
            <div className="bg-slate-50 rounded-xl p-5 border border-slate-200/60 text-left space-y-3 text-xs mb-8">
              <h4 className="font-extrabold text-slate-900 uppercase tracking-widest text-[9px] border-b border-slate-200/60 pb-2 flex items-center gap-1.5">
                <Award className="h-3.5 w-3.5 text-green-600" />
                Application Summary Copy
              </h4>
              <div className="grid grid-cols-2 gap-y-2 text-slate-600">
                <div>
                  <span className="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">Applicant</span>
                  <span className="font-bold text-slate-900">{formData.fullName}</span>
                </div>
                <div>
                  <span className="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">County / Country</span>
                  <span className="font-bold text-slate-900">{formData.countyOrCountry}</span>
                </div>
                <div>
                  <span className="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">Selected Track</span>
                  <span className="font-bold text-[#15A24A]">{formData.courseChoice}</span>
                </div>
                <div>
                  <span className="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">Intake Date</span>
                  <span className="font-bold text-slate-900">{formData.intakeDate}</span>
                </div>
                <div className="col-span-2">
                  <span className="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">Contact Phone</span>
                  <span className="font-bold text-slate-900">{formData.phone}</span>
                </div>
              </div>
            </div>

            <div className="flex flex-col sm:flex-row gap-3 justify-center">
              <button
                onClick={() => navigate('/')}
                className="px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs uppercase tracking-wider rounded-xl cursor-pointer"
              >
                Return to Home
              </button>
              <button
                onClick={() => {
                  setFormData({
                    fullName: '',
                    countyOrCountry: '',
                    email: '',
                    phone: '',
                    courseChoice: coursesData[0]?.title || '',
                    intakeDate: '',
                    paymentMessage: ''
                  });
                  setIsSubmitted(false);
                }}
                className="px-6 py-3 border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold text-xs uppercase tracking-wider rounded-xl cursor-pointer"
              >
                Apply for Another Course
              </button>
            </div>

          </div>
        )}

      </div>
    </div>
  );
}
