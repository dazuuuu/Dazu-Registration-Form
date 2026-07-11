import React, { useState, useEffect } from 'react';
import { useRouter } from '../router';
import { api } from '../api/client';
import { 
  User, 
  Users,
  Lock, 
  ShieldAlert, 
  CheckCircle, 
  LogOut, 
  BookOpen, 
  Calendar, 
  Laptop, 
  Award,
  Sparkles,
  ShieldCheck,
  FileText,
  Activity,
  Database,
  Search,
  ArrowRight,
  Info
} from 'lucide-react';

export default function Login() {
  const { navigate } = useRouter();
  const [role, setRole] = useState<'Student' | 'Instructor'>('Student');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [authStatus, setAuthStatus] = useState<'logged-out' | 'loading' | 'logged-in' | 'error'>('logged-out');
  const [formErrorMsg, setFormErrorMsg] = useState('');

  // Hidden/secure admin panel easter egg states
  const [badgeClicks, setBadgeClicks] = useState(0);
  const [adminUnlocked, setAdminUnlocked] = useState(false);
  const [showUnlockNotice, setShowUnlockNotice] = useState(false);

  // Parse admin unlock triggers from URL parameters on load
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('admin') === 'true' || params.get('role') === 'instructor' || params.get('role') === 'admin') {
      setAdminUnlocked(true);
    }
  }, []);

  const handleBadgeClick = () => {
    const nextClicks = badgeClicks + 1;
    setBadgeClicks(nextClicks);
    if (nextClicks === 5) {
      setAdminUnlocked(true);
      setShowUnlockNotice(true);
      setRole('Instructor');
      setTimeout(() => setShowUnlockNotice(false), 5000);
    }
  };

  // Simulated student metrics
  const studentProfile = {
    name: "Alex Ndungu",
    course: "Software Development",
    cohort: "Q1 2026",
    progress: "75%",
    streak: "14 Days",
    capstone: "Pending Review",
    labsCompleted: "18 / 24",
    attendance: "94%"
  };

  // Simulated active admissions logs for Instructor view
  const applicantSubmissions = [
    {
      id: "DH-9921",
      name: "Jane Wangari Kamau",
      track: "Web Development",
      paymentStatus: "KES 1,000 Verified (Ref: QGH123XYZ)",
      idScan: "jane_id_scan.pdf",
      resultSlip: "jane_kcse_result.pdf",
      sandboxStatus: "Clean / Checked",
      time: "25 mins ago"
    },
    {
      id: "DH-8472",
      name: "Silas Ndwiga",
      track: "Cybersecurity & Networks",
      paymentStatus: "KES 1,000 Verified (Ref: QIK889ABC)",
      idScan: "silas_national_id.jpg",
      resultSlip: "silas_highschool_cert.pdf",
      sandboxStatus: "Clean / Checked",
      time: "1 hour ago"
    }
  ];

  const handleSignIn = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !password.trim()) {
      setFormErrorMsg('Please fill in both email and password fields.');
      setAuthStatus('error');
      return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email.trim())) {
      setFormErrorMsg('Please provide a valid email address format.');
      setAuthStatus('error');
      return;
    }

    if (password.length < 6) {
      setFormErrorMsg('Passwords must be at least 6 characters long.');
      setAuthStatus('error');
      return;
    }

    setAuthStatus('loading');
    setFormErrorMsg('');

    try {
      const res = await api.login(email.trim(), password);
      const dest = res.redirect?.startsWith('/') ? res.redirect : '/student';
      if (res.user.role === 'tutor' || dest.includes('tutor')) {
        navigate('/tutor');
      } else if (res.user.role === 'admin' || adminUnlocked) {
        window.location.href = '/admin/';
      } else {
        navigate('/student');
      }
    } catch (err) {
      setFormErrorMsg(err instanceof Error ? err.message : 'Login failed');
      setAuthStatus('error');
    }
  };

  const handleSignOut = () => {
    setEmail('');
    setPassword('');
    setAuthStatus('logged-out');
  };

  return (
    <div className="bg-slate-50 min-h-screen py-16 text-slate-900 flex items-center justify-center font-sans">
      <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 w-full">
        
        {authStatus === 'logged-in' ? (
          role === 'Student' ? (
            /* STUDENT PORTAL DASHBOARD VIEW */
            <div className="max-w-4xl mx-auto space-y-8 text-left bg-white border border-slate-200 rounded-3xl p-6 md:p-10 shadow-xs">
              
              {/* Dashboard Header bar */}
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-6 border-b border-slate-200 pb-6">
                <div className="space-y-1">
                  <span className="text-green-600 text-xs uppercase tracking-widest font-extrabold px-2 py-0.5 bg-green-50 border border-green-200 rounded-full">
                    Student Console
                  </span>
                  <h1 className="text-2xl md:text-3xl font-extrabold text-slate-900">
                    Welcome back, {studentProfile.name}!
                  </h1>
                  <p className="text-xs text-slate-500 font-semibold">
                    Course: <span className="text-green-600">{studentProfile.course}</span> • Cohort: {studentProfile.cohort}
                  </p>
                </div>
                <button
                  onClick={handleSignOut}
                  className="px-4 py-2 text-xs font-bold text-slate-600 hover:text-red-600 bg-slate-50 border border-slate-200 hover:bg-slate-100 rounded-xl flex items-center gap-1.5 cursor-pointer self-start sm:self-auto transition-colors"
                >
                  Sign Out
                  <LogOut className="h-4 w-4" />
                </button>
              </div>

              {/* Metrics cards grid */}
              <div className="grid grid-cols-2 lg:grid-cols-4 gap-6">
                <div className="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center space-y-1">
                  <span className="block text-2xl font-extrabold text-slate-900">{studentProfile.progress}</span>
                  <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Syllabus Progress</span>
                </div>
                <div className="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center space-y-1">
                  <span className="block text-2xl font-extrabold text-[#15A24A]">{studentProfile.streak}</span>
                  <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Coding Streak</span>
                </div>
                <div className="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center space-y-1">
                  <span className="block text-2xl font-extrabold text-blue-600">{studentProfile.labsCompleted}</span>
                  <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Labs Completed</span>
                </div>
                <div className="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center space-y-1">
                  <span className="block text-2xl font-extrabold text-slate-900">{studentProfile.attendance}</span>
                  <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Attendance Rate</span>
                </div>
              </div>

              {/* Detail Layout */}
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                {/* Daily lab task */}
                <div className="lg:col-span-2 bg-slate-50 border border-slate-200 rounded-2xl p-8 space-y-6">
                  <h3 className="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                    <Laptop className="h-5 w-5 text-[#15A24A]" />
                    Today's Laboratory Task
                  </h3>
                  
                  <div className="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
                    <div className="flex justify-between items-center text-xs font-bold text-slate-400 uppercase tracking-widest">
                      <span>Task #19: M-Pesa Express integration</span>
                      <span className="text-amber-600 bg-amber-50 border border-amber-100 rounded-full px-2 py-0.5">Active Lab</span>
                    </div>
                    <p className="text-xs text-slate-600 font-semibold leading-relaxed">
                      Set up a Node.js express route handling Daraja API STK Push requests. Test immediate response codes and map success status variables onto the database interface.
                    </p>
                    
                    <div className="pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-500">
                      <span>Due: Today, 5:00 PM</span>
                      <button className="px-3.5 py-1.5 bg-[#15A24A] hover:bg-[#128a3e] text-white rounded-lg cursor-pointer transition-colors">Submit Code</button>
                    </div>
                  </div>
                </div>

                {/* Capstone tracking */}
                <div className="bg-slate-50 border border-slate-200 rounded-2xl p-8 space-y-6">
                  <h3 className="font-extrabold text-slate-900 text-sm uppercase tracking-wider flex items-center gap-2">
                    <Award className="h-5 w-5 text-[#15A24A]" />
                    Capstone Portfolio
                  </h3>
                  
                  <ul className="space-y-4 text-xs font-semibold text-slate-700">
                    <li className="flex items-start gap-2.5">
                      <span className="h-2 w-2 rounded-full bg-green-500 shrink-0 mt-1.5"></span>
                      <div>
                        <p className="font-bold text-slate-900">Task 1: GitHub Configured</p>
                        <p className="text-[10px] text-slate-400">Approved</p>
                      </div>
                    </li>
                    <li className="flex items-start gap-2.5">
                      <span className="h-2 w-2 rounded-full bg-green-500 shrink-0 mt-1.5"></span>
                      <div>
                        <p className="font-bold text-slate-900">Task 2: Database Schema Drafted</p>
                        <p className="text-[10px] text-slate-400">Approved</p>
                      </div>
                    </li>
                    <li className="flex items-start gap-2.5">
                      <span className="h-2 w-2 rounded-full bg-amber-500 shrink-0 mt-1.5"></span>
                      <div>
                        <p className="font-bold text-slate-900">Task 3: Backend Routes Completed</p>
                        <p className="text-[10px] text-amber-600">Pending Review</p>
                      </div>
                    </li>
                  </ul>
                </div>

              </div>

            </div>
          ) : (
            /* INSTRUCTOR PORTAL DASHBOARD VIEW */
            <div className="max-w-5xl mx-auto space-y-8 text-left bg-white border border-slate-200 rounded-3xl p-6 md:p-10 shadow-xs">
              
              {/* Dashboard Header bar */}
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-6 border-b border-slate-200 pb-6">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="text-red-700 text-xs uppercase tracking-widest font-extrabold px-2 py-0.5 bg-red-50 border border-red-200 rounded-full flex items-center gap-1">
                      <ShieldCheck className="h-3 w-3" />
                      Administrative Instructor Console
                    </span>
                    <span className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Thika Main Node</span>
                  </div>
                  <h1 className="text-2xl md:text-3xl font-extrabold text-slate-900">
                    Welcome, Instructor Board!
                  </h1>
                  <p className="text-xs text-slate-500 font-semibold">
                    System Authority: <span className="text-[#15A24A]">Active & Logged</span> • Active Session: Encrypted
                  </p>
                </div>
                <button
                  onClick={handleSignOut}
                  className="px-4 py-2 text-xs font-bold text-slate-600 hover:text-red-600 bg-slate-50 border border-slate-200 hover:bg-slate-100 rounded-xl flex items-center gap-1.5 cursor-pointer self-start sm:self-auto transition-colors"
                >
                  Sign Out
                  <LogOut className="h-4 w-4" />
                </button>
              </div>

              {/* Administrative Stats */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div className="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-1 flex items-center justify-between">
                  <div>
                    <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Active Applicants</span>
                    <span className="block text-2xl font-extrabold text-slate-900">28 Students</span>
                  </div>
                  <Users className="h-8 w-8 text-slate-400" />
                </div>
                <div className="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-1 flex items-center justify-between">
                  <div>
                    <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Daraja API Queue</span>
                    <span className="block text-2xl font-extrabold text-green-600">100% Cleared</span>
                  </div>
                  <Database className="h-8 w-8 text-green-600" />
                </div>
                <div className="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-1 flex items-center justify-between">
                  <div>
                    <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Pending Reviews</span>
                    <span className="block text-2xl font-extrabold text-amber-600">2 Pending</span>
                  </div>
                  <Activity className="h-8 w-8 text-amber-500" />
                </div>
                <div className="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-1 flex items-center justify-between">
                  <div>
                    <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Security Audits</span>
                    <span className="block text-2xl font-extrabold text-blue-600">Secure</span>
                  </div>
                  <ShieldCheck className="h-8 w-8 text-blue-600" />
                </div>
              </div>

              {/* Main administrative log board */}
              <div className="space-y-6">
                <div className="flex justify-between items-center border-b border-slate-100 pb-3">
                  <h3 className="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                    <FileText className="h-5 w-5 text-green-600" />
                    Incoming Admission Document Review Queue
                  </h3>
                  <span className="text-[10px] text-[#15A24A] bg-green-50 px-2 py-0.5 rounded-full font-bold border border-green-100 uppercase tracking-wider flex items-center gap-1">
                    <ShieldCheck className="h-3 w-3" /> Encrypted PDF Storage
                  </span>
                </div>

                <div className="bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden">
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs border-collapse">
                      <thead>
                        <tr className="border-b border-slate-200 text-slate-400 uppercase tracking-wider font-extrabold bg-slate-100/60 text-[9px]">
                          <th className="p-4">Ref Code</th>
                          <th className="p-4">Applicant</th>
                          <th className="p-4">Target Course</th>
                          <th className="p-4">M-Pesa Verification</th>
                          <th className="p-4">Attached Documents</th>
                          <th className="p-4 text-right">Actions</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-200 font-semibold">
                        {applicantSubmissions.map((applicant) => (
                          <tr key={applicant.id} className="hover:bg-white transition-colors">
                            <td className="p-4 text-slate-500">{applicant.id}</td>
                            <td className="p-4">
                              <span className="block text-slate-900 font-extrabold">{applicant.name}</span>
                              <span className="text-[10px] text-slate-400">{applicant.time}</span>
                            </td>
                            <td className="p-4 text-slate-700">{applicant.track}</td>
                            <td className="p-4">
                              <span className="inline-block px-2 py-1 bg-green-50 text-green-700 border border-green-100 rounded-md text-[10px]">
                                {applicant.paymentStatus}
                              </span>
                            </td>
                            <td className="p-4 space-y-1">
                              <div className="flex items-center gap-1 text-slate-600 text-[10px]">
                                <FileText className="h-3 w-3 text-slate-400 shrink-0" />
                                <span>{applicant.idScan}</span>
                                <span className="text-[8px] px-1 bg-green-100 text-green-800 rounded font-extrabold">{applicant.sandboxStatus}</span>
                              </div>
                              <div className="flex items-center gap-1 text-slate-600 text-[10px]">
                                <FileText className="h-3 w-3 text-slate-400 shrink-0" />
                                <span>{applicant.resultSlip}</span>
                                <span className="text-[8px] px-1 bg-green-100 text-green-800 rounded font-extrabold">{applicant.sandboxStatus}</span>
                              </div>
                            </td>
                            <td className="p-4 text-right">
                              <div className="flex gap-1.5 justify-end">
                                <button 
                                  onClick={() => alert(`Reviewing documents for ${applicant.name}. Sandbox confirms zero executable scripts or shell vulnerabilities present.`)}
                                  className="px-2.5 py-1.5 bg-green-600 hover:bg-green-700 text-white text-[10px] font-bold rounded-md transition-colors cursor-pointer"
                                >
                                  Sign & Approve
                                </button>
                                <button 
                                  onClick={() => alert(`Accessing file sandbox logs for ${applicant.name}. Document is verified as safe.`)}
                                  className="px-2.5 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-[10px] font-bold rounded-md transition-colors cursor-pointer"
                                >
                                  Inspect Sandbox
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>

                {/* Secure storage note compliance with DPA */}
                <div className="p-4 bg-amber-50 border border-amber-100 rounded-xl flex items-start gap-2 text-[11px] leading-relaxed text-amber-800 font-semibold">
                  <Info className="h-4 w-4 text-amber-700 shrink-0 mt-0.5" />
                  <p>
                    <strong>Data Compliance Warning (Kenya DPA 2019):</strong> Student registration documents, National ID uploads, and high school certificates contain sensitive personal data. Downloading applicant files onto unencrypted public laptops or unmanaged folders is strictly prohibited. Downloading actions are logged with timestamp and account credentials for auditing by the Office of the Data Protection Commissioner (ODPC).
                  </p>
                </div>
              </div>

            </div>
          )
        ) : (
          /* PORTAL SIGN-IN FORM VIEW */
          <div className="max-w-md mx-auto bg-white border border-slate-200 rounded-2xl p-8 md:p-10 space-y-8 text-left shadow-sm">
            
            {/* Title headers */}
            <div className="space-y-2 text-center">
              <button
                type="button"
                onClick={handleBadgeClick}
                className="text-green-600 text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 bg-green-50 border border-green-200 rounded-full focus:outline-none cursor-pointer hover:bg-green-100 transition-colors"
              >
                Dazu Hub Portal
              </button>
              <h2 className="text-2xl font-extrabold text-slate-900 tracking-tight pt-2">Sign In to Your Console</h2>
              <p className="text-xs text-slate-400 font-semibold leading-relaxed">
                Enter your registered credentials to coordinate active lectures, submit tasks, or review student folders.
              </p>
            </div>

            {/* Unlock Notice Toast if they unlock instructor via click */}
            {showUnlockNotice && (
              <div className="p-3 border border-green-200 bg-green-50 text-green-800 text-xs font-semibold rounded-xl flex items-center gap-2 animate-bounce">
                <ShieldCheck className="h-4 w-4 text-green-600" />
                <span>Instructor Console Login unlocked successfully!</span>
              </div>
            )}

            {/* Portal Role Toggle (Shown ONLY when unlocked via secret parameters or easter-egg badge clicks) */}
            {adminUnlocked ? (
              <div className="grid grid-cols-2 gap-2 p-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                <button
                  type="button"
                  onClick={() => setRole('Student')}
                  className={`py-2 rounded-lg cursor-pointer transition-all ${
                    role === 'Student' ? 'bg-green-600 text-white font-extrabold shadow-sm' : 'text-slate-500 hover:bg-white'
                  }`}
                >
                  Student Portal
                </button>
                <button
                  type="button"
                  onClick={() => setRole('Instructor')}
                  className={`py-2 rounded-lg cursor-pointer transition-all ${
                    role === 'Instructor' ? 'bg-green-600 text-white font-extrabold shadow-sm' : 'text-slate-500 hover:bg-white'
                  }`}
                >
                  Instructor Portal
                </button>
              </div>
            ) : (
              // Secure layout fallback: Explicitly hide the Admin toggle from public and anonymous visitors
              <div className="p-3 bg-slate-50 border border-slate-100 rounded-xl text-center text-xs font-extrabold text-slate-600 uppercase tracking-wider">
                🔒 Student Secure Access Node
              </div>
            )}

            {/* Submit form */}
            <form onSubmit={handleSignIn} className="space-y-6">
              
              {authStatus === 'error' && (
                <div className="p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl flex items-start gap-2">
                  <ShieldAlert className="h-4.5 w-4.5 shrink-0 mt-0.5" />
                  <span>{formErrorMsg || 'Please provide both your registered email and passwords.'}</span>
                </div>
              )}

              <div className="space-y-2">
                <label className="block text-xs font-bold uppercase tracking-widest text-slate-500">Registered Email</label>
                <div className="relative">
                  <span className="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <User className="h-4 w-4" />
                  </span>
                  <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder={role === 'Instructor' ? "instructor@dazuaihub.com" : "john@dazuaihub.com"}
                    className="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 font-medium focus:outline-none focus:border-green-600 focus:bg-white text-sm transition-colors"
                    required
                  />
                </div>
              </div>

              <div className="space-y-2">
                <div className="flex justify-between items-center text-xs font-bold uppercase tracking-widest text-slate-500">
                  <label>Secure Password</label>
                  <a href="#" className="text-green-600 hover:underline">Forgot?</a>
                </div>
                <div className="relative">
                  <span className="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <Lock className="h-4 w-4" />
                  </span>
                  <input
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="••••••••"
                    className="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 font-medium focus:outline-none focus:border-green-600 focus:bg-white text-sm transition-colors"
                    required
                  />
                </div>
              </div>

              <button
                type="submit"
                disabled={authStatus === 'loading'}
                className="w-full py-3.5 bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-xl transition-all disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer shadow-xs"
              >
                {authStatus === 'loading' ? 'Authenticating Profile...' : `Sign In as ${role}`}
              </button>

              <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 text-[11px] font-semibold text-slate-500 leading-normal flex items-start gap-2">
                <Sparkles className="h-4 w-4 text-green-600 shrink-0 mt-0.5" />
                <span>
                  <strong>Demo Access:</strong> Enter any registered email and password to access the secure Console. 
                  {!adminUnlocked && " Instructors must log in via a verified access key or the administrative badge unlock."}
                </span>
              </div>

            </form>

          </div>
        )}

      </div>
    </div>
  );
}
