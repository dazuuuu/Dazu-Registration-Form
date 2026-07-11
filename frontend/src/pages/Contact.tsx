import React, { useState } from 'react';
import { 
  Phone, 
  MapPin, 
  Mail, 
  Clock, 
  Send, 
  CheckCircle, 
  Info,
  ExternalLink,
  ShieldCheck
} from 'lucide-react';

export default function Contact() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    subject: 'General Inquiry',
    message: ''
  });

  const [formStatus, setFormStatus] = useState<'idle' | 'loading' | 'success' | 'error'>('idle');
  const [formErrorMsg, setFormErrorMsg] = useState<string>('');

  // Security honeypot to trap automated spam bots
  const [botField, setBotField] = useState('');

  // Math Captcha to prevent script injections and automated spamming
  const [captcha, setCaptcha] = useState({ num1: 0, num2: 0, answer: 0 });
  const [captchaInput, setCaptchaInput] = useState('');

  const generateCaptcha = () => {
    const num1 = Math.floor(Math.random() * 9) + 1;
    const num2 = Math.floor(Math.random() * 9) + 1;
    setCaptcha({ num1, num2, answer: num1 + num2 });
    setCaptchaInput('');
  };

  React.useEffect(() => {
    generateCaptcha();
  }, []);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    // 1. Spam Bot Trap (Honeypot check)
    if (botField) {
      setFormStatus('success');
      return;
    }

    // 2. Completeness validation
    if (!formData.name.trim() || !formData.phone.trim() || !formData.message.trim()) {
      setFormErrorMsg('Please fill in all the required fields (Full Name, Phone Number, and Message).');
      setFormStatus('error');
      return;
    }

    // 3. Email format regex validation (optional but if provided, must be valid)
    if (formData.email.trim()) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(formData.email.trim())) {
        setFormErrorMsg('Please provide a valid email address (e.g. name@domain.com).');
        setFormStatus('error');
        return;
      }
    }

    // 4. Phone Number Format Validation (must be valid)
    const phoneRegex = /^\+?[0-9\s\-]{9,15}$/;
    if (!phoneRegex.test(formData.phone.trim())) {
      setFormErrorMsg('Please provide a valid phone number (at least 9 digits, e.g. 0712345678).');
      setFormStatus('error');
      return;
    }

    // 5. Anti-Spam Captcha Validation
    if (parseInt(captchaInput.trim(), 10) !== captcha.answer) {
      setFormErrorMsg('Incorrect security answer. Please solve the simple math puzzle to proceed.');
      setFormStatus('error');
      generateCaptcha();
      return;
    }

    setFormStatus('loading');
    setFormErrorMsg('');
    
    // Simulate API form dispatch
    setTimeout(() => {
      setFormStatus('success');
      setFormData({
        name: '',
        email: '',
        phone: '',
        subject: 'General Inquiry',
        message: ''
      });
      generateCaptcha();
    }, 1500);
  };

  return (
    <div className="bg-white min-h-screen py-16 text-slate-900">
      <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
        
        {/* Header Title */}
        <div className="max-w-2xl text-left space-y-4 mb-16">
          <span className="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full w-fit">
            Get in Touch
          </span>
          <h1 className="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900">
            Contact Dazu Hub
          </h1>
          <p className="text-slate-600 font-medium leading-relaxed">
            Have questions about tuition fees, study plans, or physical computer labs? Talk directly with our registrar or visit us in Thika Town.
          </p>
        </div>

        {/* Info Grid & Form layout */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
          
          {/* Left Column: Direct info panels & Location map */}
          <div className="lg:col-span-5 space-y-8">
            
            <div className="bg-slate-50 border border-slate-200 rounded-2xl p-8 space-y-6">
              <h3 className="text-lg font-bold text-slate-900">Institute Directory</h3>
              
              <ul className="space-y-6 text-sm">
                <li className="flex items-start gap-4">
                  <div className="p-3 bg-white border border-slate-200 text-green-600 rounded-xl">
                    <Phone className="h-5 w-5" />
                  </div>
                  <div>
                    <span className="block text-slate-400 uppercase tracking-wider text-[10px] font-bold">Call / WhatsApp</span>
                    <span className="text-base font-bold text-slate-900">0750 116 600</span>
                  </div>
                </li>

                <li className="flex items-start gap-4">
                  <div className="p-3 bg-white border border-slate-200 text-green-600 rounded-xl">
                    <Mail className="h-5 w-5" />
                  </div>
                  <div>
                    <span className="block text-slate-400 uppercase tracking-wider text-[10px] font-bold">Email Admissions</span>
                    <span className="text-base font-bold text-slate-900">info@dazuaihub.com</span>
                  </div>
                </li>

                <li className="flex items-start gap-4">
                  <div className="p-3 bg-white border border-slate-200 text-green-600 rounded-xl">
                    <MapPin className="h-5 w-5" />
                  </div>
                  <div>
                    <span className="block text-slate-400 uppercase tracking-wider text-[10px] font-bold">Campus Location</span>
                    <span className="text-base font-bold text-slate-900">Witeithie House, Room 529, Thika Town</span>
                  </div>
                </li>

                <li className="flex items-start gap-4">
                  <div className="p-3 bg-white border border-slate-200 text-green-600 rounded-xl">
                    <Clock className="h-5 w-5" />
                  </div>
                  <div>
                    <span className="block text-slate-400 uppercase tracking-wider text-[10px] font-bold">Office Hours</span>
                    <span className="text-base font-bold text-slate-900">Monday - Saturday: 8:00 AM - 5:00 PM</span>
                  </div>
                </li>
              </ul>
            </div>

            {/* Embedded maps guide panel */}
            <div className="bg-slate-50 border border-slate-200 rounded-2xl p-6 space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Campus Directions</h3>
                <a 
                  href="https://maps.google.com" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="text-xs text-green-600 font-bold hover:underline flex items-center gap-1"
                >
                  Open in Google Maps <ExternalLink className="h-3 w-3" />
                </a>
              </div>
              
              {/* Maps simulation layout */}
              <div className="bg-white border border-slate-200 rounded-xl p-8 text-center space-y-4 aspect-[4/3] flex flex-col justify-center items-center">
                <MapPin className="h-10 w-10 text-green-600 animate-bounce" />
                <div className="space-y-1">
                  <p className="font-extrabold text-slate-900">Witeithie House, Thika</p>
                  <p className="text-xs font-semibold text-slate-400 uppercase tracking-widest">Room 529 (5th Floor)</p>
                </div>
                <p className="text-xs text-slate-500 font-medium max-w-xs leading-relaxed">
                  Located directly within Thika Town, near principal transport stages, making daily commutes seamless for local students.
                </p>
              </div>
            </div>

          </div>

          {/* Right Column: Contact Inquiry Form */}
          <div className="lg:col-span-7">
            <div className="bg-white border border-slate-200 rounded-2xl p-8 md:p-10 space-y-8 text-left">
              <div className="space-y-2">
                <h3 className="text-xl font-extrabold text-slate-900">Inquiry Intake Form</h3>
                <p className="text-xs text-slate-500 font-semibold leading-relaxed">
                  Fill out the secure form below. Our admissions registrar will review details and follow up within 24 business hours.
                </p>
              </div>

              {formStatus === 'success' ? (
                <div className="p-8 border border-green-200 bg-green-50 rounded-2xl text-center space-y-4">
                  <CheckCircle className="h-12 w-12 text-green-600 mx-auto" />
                  <div className="space-y-1">
                    <h4 className="text-lg font-bold text-slate-900">Message Delivered Successfully!</h4>
                    <p className="text-xs text-slate-600 font-medium">
                      Thank you for contacting Dazu Hub Institute. We have logged your request. Our staff will call your phone number shortly.
                    </p>
                  </div>
                  <button
                    onClick={() => setFormStatus('idle')}
                    className="mt-4 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-xl cursor-pointer"
                  >
                    Send Another Message
                  </button>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-6">
                  
                  {formStatus === 'error' && (
                    <div className="p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl flex items-start gap-2.5">
                      <Info className="h-4.5 w-4.5 shrink-0 mt-0.5" />
                      <span>{formErrorMsg || 'Please ensure you provide your full name, phone number, and message content before submitting.'}</span>
                    </div>
                  )}

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div className="space-y-2">
                      <label className="block text-xs font-bold uppercase tracking-widest text-slate-500">Full Name *</label>
                      <input
                        type="text"
                        name="name"
                        value={formData.name}
                        onChange={handleInputChange}
                        placeholder="John Doe"
                        className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 font-medium focus:outline-none focus:border-green-600 focus:bg-white text-sm"
                        required
                      />
                    </div>
                    <div className="space-y-2">
                      <label className="block text-xs font-bold uppercase tracking-widest text-slate-500">Phone Number *</label>
                      <input
                        type="tel"
                        name="phone"
                        value={formData.phone}
                        onChange={handleInputChange}
                        placeholder="0700 000 000"
                        className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 font-medium focus:outline-none focus:border-green-600 focus:bg-white text-sm"
                        required
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div className="space-y-2">
                      <label className="block text-xs font-bold uppercase tracking-widest text-slate-500">Email Address</label>
                      <input
                        type="email"
                        name="email"
                        value={formData.email}
                        onChange={handleInputChange}
                        placeholder="john@example.com"
                        className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 font-medium focus:outline-none focus:border-green-600 focus:bg-white text-sm"
                      />
                    </div>
                    <div className="space-y-2">
                      <label className="block text-xs font-bold uppercase tracking-widest text-slate-500">Subject Topic</label>
                      <select
                        name="subject"
                        value={formData.subject}
                        onChange={handleInputChange}
                        className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:outline-none focus:border-green-600 focus:bg-white text-sm"
                      >
                        <option>General Inquiry</option>
                        <option>Course Admissions</option>
                        <option>Payment/Fees Plan</option>
                        <option>Corporate Partnership</option>
                      </select>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <label className="block text-xs font-bold uppercase tracking-widest text-slate-500">Message Details *</label>
                    <textarea
                      name="message"
                      rows={5}
                      value={formData.message}
                      onChange={handleInputChange}
                      placeholder="Specify your course interest or general question here..."
                      className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 font-medium focus:outline-none focus:border-green-600 focus:bg-white text-sm"
                      required
                    ></textarea>
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

                  <button
                    type="submit"
                    disabled={formStatus === 'loading'}
                    className="w-full sm:w-auto px-6 py-3.5 bg-green-600 hover:bg-green-700 text-white font-bold text-sm rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                  >
                    {formStatus === 'loading' ? 'Delivering Message...' : 'Send Inquiry'}
                    <Send className="h-4 w-4" />
                  </button>

                </form>
              )}
            </div>
          </div>

        </div>

      </div>
    </div>
  );
}
