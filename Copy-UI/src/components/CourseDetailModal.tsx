import React from 'react';
import { 
  X, Play, Share2, Link as LinkIcon, MessageCircle, 
  Globe, Book, FileText, Calendar as CalendarIcon, ShieldCheck,
  CheckCircle, MapPin, Monitor
} from 'lucide-react';

interface Course {
  id: number;
  title: string;
  category: string;
  description: string;
  duration: string;
  image: string;
}

interface CourseDetailModalProps {
  course: Course;
  onClose: () => void;
}

export default function CourseDetailModal({ course, onClose }: CourseDetailModalProps) {
  // Use course details or defaults for the specific 'Video editing' prompt requirements
  const isVideoEditing = course.title.toLowerCase().includes('video editing');
  const title = isVideoEditing ? "Video editing & content creation" : course.title;
  const category = isVideoEditing ? "Software & Web" : course.category;
  const description = isVideoEditing 
    ? "Master video editing and content creation for TikTok, YouTube, and social media. Learn to produce engaging videos that grow your audience and open opportunities in content creation and digital marketing."
    : course.description;
  const duration = isVideoEditing ? "1 Months" : course.duration;
  const fee = isVideoEditing ? "KES 30,000" : "KES 30,000";

  const whatYouWillLearn = isVideoEditing 
    ? ["CapCut", "Premiere Pro", "TikTok", "YouTube content", "Meta"]
    : ["Core Fundamentals", "Advanced Techniques", "Industry Standard Tools", "Real-world Projects", "Portfolio Building"];

  const requirements = isVideoEditing
    ? [
        "A laptop, mean grade from D and above",
        "A good and stable internet connection for online class"
      ]
    : [
        "A laptop with minimum 8GB RAM",
        "Basic computer literacy",
        "Stable internet connection"
      ];

  return (
    <div className="fixed inset-0 z-[100] bg-white overflow-y-auto flex flex-col md:flex-row">
      {/* Mobile Close Button */}
      <button 
        onClick={onClose}
        className="md:hidden fixed top-4 right-4 z-[110] w-10 h-10 bg-black/10 backdrop-blur-md text-slate-900 rounded-full flex items-center justify-center"
      >
        <X size={20} />
      </button>

      {/* Main Content Area (Left on Desktop, Top on Mobile) */}
      <div className="flex-1 p-4 md:p-8 lg:p-12 pb-24 md:pb-12 max-w-4xl mx-auto w-full">
        
        {/* Desktop Close Button */}
        <button 
          onClick={onClose}
          className="hidden md:flex mb-6 items-center gap-2 text-slate-500 hover:text-slate-900 font-bold transition-colors"
        >
          <X size={20} /> Close preview
        </button>

        {/* Video Player */}
        <div className="relative w-full aspect-video bg-slate-900 rounded-2xl overflow-hidden mb-8 group cursor-pointer shadow-lg">
          <img src={course.image} alt={title} className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" />
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="w-16 h-16 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center border border-white/30 group-hover:bg-white/30 transition-colors shadow-2xl">
              <Play size={28} className="text-white ml-1 fill-white" />
            </div>
          </div>
        </div>

        {/* Share Section */}
        <div className="bg-slate-50 border border-slate-100 rounded-2xl p-5 mb-8">
          <div className="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between mb-4">
            <div>
              <h4 className="font-bold text-slate-900 flex items-center gap-2">
                <Share2 size={18} className="text-green-600" /> Share this course
              </h4>
              <p className="text-sm text-slate-600 mt-1">Apply for {title} at Dazu hub Institute. Friends who open this link see the course cover, title and description in the preview.</p>
            </div>
          </div>
          
          <div className="flex items-center gap-2 bg-white p-2 rounded-lg border border-slate-200 mb-4 overflow-hidden">
            <span className="text-xs text-slate-500 truncate flex-1 pl-2 select-all">https://dazuaihub.com/courses/share.php?id={course.id}</span>
          </div>

          <div className="flex flex-wrap gap-2">
            <button className="flex items-center gap-2 text-xs font-bold bg-white border border-slate-200 px-3 py-2 rounded-lg hover:bg-slate-50">
              <LinkIcon size={14} /> Copy Link
            </button>
            <button className="flex items-center gap-2 text-xs font-bold bg-[#25D366]/10 text-[#25D366] px-3 py-2 rounded-lg hover:bg-[#25D366]/20">
              <MessageCircle size={14} /> WhatsApp
            </button>
            <button className="flex items-center gap-2 text-xs font-bold bg-[#1877F2]/10 text-[#1877F2] px-3 py-2 rounded-lg hover:bg-[#1877F2]/20">
              <Globe size={14} /> Facebook
            </button>
            <button className="flex items-center gap-2 text-xs font-bold bg-slate-900 text-white px-3 py-2 rounded-lg hover:bg-slate-800">
              <Share2 size={14} /> Share
            </button>
          </div>
        </div>

        {/* Program Details */}
        <div className="mb-10">
          <div className="inline-flex items-center gap-2 text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">
            <Book size={16} className="text-green-600" /> Selected Program Details
          </div>
          <h2 className="text-3xl md:text-4xl font-extrabold text-slate-900 mb-2 leading-tight">{title}</h2>
          <span className="inline-block bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-6">
            {category}
          </span>
          
          <h3 className="text-lg font-bold text-slate-900 mb-2">Overview</h3>
          <p className="text-slate-600 leading-relaxed mb-6">{description}</p>

          <div className="grid grid-cols-2 gap-4 mb-8">
            <div className="bg-slate-50 p-4 rounded-xl border border-slate-100">
              <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Duration</div>
              <div className="text-lg font-black text-slate-900">{duration}</div>
            </div>
            <div className="bg-slate-50 p-4 rounded-xl border border-slate-100">
              <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Tuition Fee</div>
              <div className="text-lg font-black text-green-600">{fee}</div>
            </div>
          </div>

          <h3 className="text-lg font-bold text-slate-900 mb-4">What you will learn</h3>
          <div className="flex flex-wrap gap-2 mb-8">
            {whatYouWillLearn.map((skill, idx) => (
              <span key={idx} className="bg-slate-100 text-slate-700 text-sm font-semibold px-4 py-2 rounded-lg border border-slate-200">
                {skill}
              </span>
            ))}
          </div>

          <h3 className="text-lg font-bold text-slate-900 mb-4">Requirements</h3>
          <ul className="space-y-3 mb-8">
            {requirements.map((req, idx) => (
              <li key={idx} className="flex items-start gap-3">
                <CheckCircle size={20} className="text-green-600 flex-shrink-0 mt-0.5" />
                <span className="text-slate-700">{req}</span>
              </li>
            ))}
          </ul>
        </div>

      </div>

      {/* Enrollment Sidebar (Right on Desktop, Bottom sheet style on Mobile) */}
      <div className="w-full md:w-[400px] lg:w-[480px] bg-slate-50 border-l border-slate-200 p-4 md:p-8 flex-shrink-0 overflow-y-auto">
        <div className="sticky top-8">
          
          {/* Form Header */}
          <div className="mb-8">
            <h3 className="text-2xl font-black text-slate-900 mb-2">Enrol</h3>
            <p className="text-sm text-slate-600">Name, email, course, and online or physical classes. We email your registration number straight away.</p>
          </div>

          {/* Fee Structure Summary */}
          <div className="bg-white rounded-xl border border-slate-200 p-5 mb-8 shadow-sm">
            <h4 className="font-bold text-slate-900 mb-4">Fee structure</h4>
            <div className="flex justify-between items-center mb-4 pb-4 border-b border-slate-100">
              <span className="text-sm text-slate-600 font-medium">Course fee</span>
              <span className="font-bold text-green-600">{fee}</span>
            </div>
            
            <div className="space-y-3">
              <div className="flex items-center justify-between group">
                <div className="flex items-center gap-2">
                  <FileText size={16} className="text-red-500" />
                  <span className="text-sm font-medium text-slate-700 group-hover:text-green-600 transition-colors cursor-pointer">Download fee structure (PDF)</span>
                </div>
                <button className="text-xs font-bold text-green-600 bg-green-50 px-3 py-1.5 rounded-md hover:bg-green-100">View</button>
              </div>
              <div className="flex items-center justify-between group">
                <div className="flex items-center gap-2">
                  <FileText size={16} className="text-red-500" />
                  <span className="text-sm font-medium text-slate-700 group-hover:text-green-600 transition-colors cursor-pointer">Brochure pdf</span>
                </div>
                <button className="text-xs font-bold text-green-600 bg-green-50 px-3 py-1.5 rounded-md hover:bg-green-100">View</button>
              </div>
              <div className="flex items-center justify-between group">
                <div className="flex items-center gap-2">
                  <CalendarIcon size={16} className="text-blue-500" />
                  <span className="text-sm font-medium text-slate-700 group-hover:text-green-600 transition-colors cursor-pointer">Class schedules pdf</span>
                </div>
                <button className="text-xs font-bold text-green-600 bg-green-50 px-3 py-1.5 rounded-md hover:bg-green-100">View</button>
              </div>
            </div>
          </div>

          {/* Enrollment Form */}
          <form className="space-y-5 mb-8" onSubmit={(e) => e.preventDefault()}>
            <div>
              <label className="block text-sm font-bold text-slate-900 mb-1.5">Full name *</label>
              <input type="text" placeholder="E.g. Jane Wangari Kamau" className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-transparent transition-all shadow-sm" required />
            </div>
            
            <div>
              <label className="block text-sm font-bold text-slate-900 mb-1.5">Email address *</label>
              <input type="email" placeholder="E.g. jane.wangari@example.com" className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-transparent transition-all shadow-sm" required />
            </div>

            <div>
              <label className="block text-sm font-bold text-slate-900 mb-1.5">Course *</label>
              <select className="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-green-600 transition-all shadow-sm appearance-none cursor-pointer">
                <option value={title}>{title}</option>
              </select>
              <p className="text-[11px] text-slate-500 mt-1.5">Not the course you wanted? Pick another — the details on the left update to match.</p>
            </div>

            <div>
              <label className="block text-sm font-bold text-slate-900 mb-3">Classes *</label>
              <div className="grid grid-cols-2 gap-3">
                <label className="relative flex flex-col items-center justify-center p-4 cursor-pointer bg-white border border-slate-200 rounded-xl hover:bg-green-50 hover:border-green-200 transition-all">
                  <input type="radio" name="classType" value="online" className="peer sr-only" defaultChecked />
                  <Monitor size={24} className="mb-2 text-slate-400 peer-checked:text-green-600" />
                  <span className="text-sm font-bold text-slate-600 peer-checked:text-green-700">Online</span>
                  <div className="absolute inset-0 border-2 border-transparent peer-checked:border-green-600 rounded-xl pointer-events-none"></div>
                </label>
                
                <label className="relative flex flex-col items-center justify-center p-4 cursor-pointer bg-white border border-slate-200 rounded-xl hover:bg-green-50 hover:border-green-200 transition-all">
                  <input type="radio" name="classType" value="physical" className="peer sr-only" />
                  <MapPin size={24} className="mb-2 text-slate-400 peer-checked:text-green-600" />
                  <span className="text-sm font-bold text-slate-600 peer-checked:text-green-700">Physical (offline)</span>
                  <div className="absolute inset-0 border-2 border-transparent peer-checked:border-green-600 rounded-xl pointer-events-none"></div>
                </label>
              </div>
            </div>

            <button type="submit" className="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-xl shadow-md transition-all active:scale-[0.98]">
              Enrol now
            </button>
          </form>

          {/* Verification / Trust Badge */}
          <div className="flex items-start gap-3 bg-slate-200/50 p-4 rounded-xl border border-slate-200">
            <ShieldCheck size={24} className="text-slate-500 flex-shrink-0" />
            <div>
              <h5 className="text-sm font-bold text-slate-900 mb-1">Admissions Verification</h5>
              <p className="text-xs text-slate-600 leading-relaxed">Your payment and enrollment data is encrypted. Certificates and industrial preparation are issued upon successful completion.</p>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}
