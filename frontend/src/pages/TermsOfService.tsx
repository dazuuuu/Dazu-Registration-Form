import React from 'react';
import { useRouter } from '../router';
import { ArrowLeft, BookOpen, AlertCircle, FileText, Landmark, Clock } from 'lucide-react';

export default function TermsOfService() {
  const { navigate } = useRouter();

  return (
    <div className="bg-slate-50 min-h-screen py-16 text-slate-900 font-sans">
      <div className="max-w-[800px] mx-auto px-4 md:px-8">
        
        {/* Navigation back */}
        <button 
          onClick={() => navigate('/')}
          className="mb-8 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-green-600 transition-colors cursor-pointer focus:outline-none"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to Home
        </button>

        {/* Card Canvas */}
        <div className="bg-white border border-slate-200 rounded-2xl p-8 md:p-12 space-y-8 text-left shadow-xs">
          
          {/* Header Banner */}
          <div className="border-b border-slate-100 pb-6 space-y-3">
            <div className="flex items-center gap-2.5">
              <span className="p-2 bg-green-50 text-[#15A24A] border border-green-100 rounded-xl">
                <BookOpen className="h-6 w-6" />
              </span>
              <span className="text-[10px] uppercase tracking-widest font-extrabold text-[#15A24A] px-2.5 py-1 bg-green-50 border border-green-100 rounded-full">
                Institute Code of Study
              </span>
            </div>
            <h1 className="text-3xl font-extrabold text-slate-950 tracking-tight">Terms of Study</h1>
            <p className="text-xs text-slate-400 font-semibold uppercase tracking-wider">
              Last Updated: July 10, 2026 • Thika Town, Kenya
            </p>
          </div>

          {/* Intro block */}
          <div className="prose prose-slate text-xs sm:text-sm text-slate-600 font-medium leading-relaxed space-y-6">
            <p>
              Welcome to <strong>Dazu hub Institute</strong>. By submitting your application, paying the non-refundable admission processing fee, and enrolling in our in-person or virtual training tracks, you agree to be bound by the following comprehensive Terms of Study. Please review these rules carefully before finalizing your admission.
            </p>

            {/* Terms Summary Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 my-6">
              <div className="p-4 bg-slate-50 border border-slate-200/60 rounded-xl flex items-start gap-3">
                <Landmark className="h-5 w-5 text-[#15A24A] shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-1">Fee Structure</h4>
                  <p className="text-[11px] leading-normal text-slate-500 font-semibold">Admission fees (KES 1,000) are non-refundable and cover verification and profile setup costs.</p>
                </div>
              </div>
              <div className="p-4 bg-slate-50 border border-slate-200/60 rounded-xl flex items-start gap-3">
                <Clock className="h-5 w-5 text-[#15A24A] shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-1">Attendance & Labs</h4>
                  <p className="text-[11px] leading-normal text-slate-500 font-semibold">Students must complete 85%+ attendance and all graded lab tasks to obtain certified clearance.</p>
                </div>
              </div>
            </div>

            {/* Detailed sections */}
            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-slate-950 tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">1.</span> Enrollment Criteria and Verification
              </h3>
              <p>
                All applicants must satisfy the minimum requirements specified for their selected course track (e.g., secondary certificate or basic computer literacy). To ensure absolute legitimacy, we require applicants to upload scans of their National ID or Passport and academic transcripts. Any falsification of application credentials will lead to immediate cancellation of admission without appeal.
              </p>
            </div>

            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-[#15A24A] tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">2.</span> Fee Policies and M-Pesa Verification
              </h3>
              <p>
                Dazu hub Institute charges a mandatory, non-refundable admission registration fee of <strong>KES 1,000</strong>.
              </p>
              <ul className="list-disc pl-5 space-y-2 text-slate-500">
                <li>All admission payments must be routed through our secure M-Pesa business portal credentials (Paybill: <strong>880100</strong>, Account: <strong>681158</strong>).</li>
                <li>The transaction reference code from your payment message is automatically cross-referenced with active Daraja API logs to prevent receipt fraud.</li>
                <li>Course tuition fees are separate and structured based on payment plans outlined at student registration. Tuition fees are strictly non-refundable once class sessions start.</li>
              </ul>
            </div>

            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-[#15A24A] tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">3.</span> Code of Conduct and Lab Tasks
              </h3>
              <p>
                Dazu hub Institute emphasizes intensive, practical training. Our code of study mandates:
              </p>
              <ul className="list-disc pl-5 space-y-2 text-slate-500">
                <li><strong>Academic Integrity:</strong> Plagiarism or sharing answers for active laboratory coding tasks, projects, or capstone assignments is strictly prohibited. Students must build their own code and write their own documents.</li>
                <li><strong>Class Attendance:</strong> A minimum of 85% attendance rate is mandatory for in-person modules at our Thika Campus (Witeithie House).</li>
                <li><strong>Professional Conduct:</strong> Respectful, professional communication is required in all student chats, forum interactions, and classroom setups.</li>
              </ul>
            </div>

            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-[#15A24A] tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">4.</span> Certification and Capstone Portfolios
              </h3>
              <p>
                To qualify for the Dazu hub Institute Graduation Certificate and be connected with our network of tech internship hiring managers, students must:
              </p>
              <ul className="list-disc pl-5 space-y-2 text-slate-500">
                <li>Complete all required course core modules and laboratory exercises.</li>
                <li>Obtain passing remarks on the Capstone project presentation, verified by course instructors.</li>
                <li>Maintained clean tuition payment balances.</li>
              </ul>
            </div>

            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-[#15A24A] tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">5.</span> Limitation of Liability
              </h3>
              <p>
                Dazu hub Institute provides skill training, dynamic syllabus materials, practical sandbox environments, and placement support. While we maintain close ties with several tier-1 and tier-2 enterprises in Kenya, admission or course completion does not represent a direct guarantee of immediate employment or specific compensation figures. The student is responsible for their own performance, coding practice, and job search diligence.
              </p>
            </div>

            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-[#15A24A] tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">6.</span> Contacting the Registrar Office
              </h3>
              <p>
                For questions regarding enrollment schedules, cohort change requests, or to obtain official tuition fee sheets, please contact:
              </p>
              <p className="text-slate-500 font-semibold mt-2">
                Dazu hub Institute Registrar<br />
                Witeithie House, Room 529, Thika Town, Kenya<br />
                Phone: <strong>0750 116 600</strong><br />
                Email: <strong>info@dazuaihub.com</strong>
              </p>
            </div>

          </div>

          {/* Action button */}
          <div className="pt-8 border-t border-slate-100 text-center">
            <button
              onClick={() => navigate('/')}
              className="px-6 py-3 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-bold uppercase tracking-wider rounded-xl transition-all shadow-sm cursor-pointer"
            >
              I Accept the Terms of Study
            </button>
          </div>

        </div>

      </div>
    </div>
  );
}
