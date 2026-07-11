import React from 'react';
import { useRouter } from '../router';
import { ArrowLeft, ShieldCheck, FileText, Lock, Users, Eye } from 'lucide-react';

export default function PrivacyPolicy() {
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
                <ShieldCheck className="h-6 w-6" />
              </span>
              <span className="text-[10px] uppercase tracking-widest font-extrabold text-[#15A24A] px-2.5 py-1 bg-green-50 border border-green-100 rounded-full">
                ODPC Registered & Compliant
              </span>
            </div>
            <h1 className="text-3xl font-extrabold text-slate-950 tracking-tight">Privacy Policy</h1>
            <p className="text-xs text-slate-400 font-semibold uppercase tracking-wider">
              Last Updated: July 10, 2026 • Compliant with Kenya's Data Protection Act, 2019
            </p>
          </div>

          {/* Intro block */}
          <div className="prose prose-slate text-xs sm:text-sm text-slate-600 font-medium leading-relaxed space-y-6">
            <p>
              At <strong>Dazu hub Institute</strong> (registered as Dazu hub), your privacy and data security are of absolute importance to us. This Privacy Policy outlines our strict practices regarding the collection, processing, storage, and retention of student or applicant data in full compliance with the <strong>Kenya Data Protection Act, 2019</strong> and the guidelines set forth by the <strong>Office of the Data Protection Commissioner (ODPC)</strong>.
            </p>

            {/* Quick Summary Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 my-6">
              <div className="p-4 bg-slate-50 border border-slate-200/60 rounded-xl flex items-start gap-3">
                <FileText className="h-5 w-5 text-[#15A24A] shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-1">Data We Collect</h4>
                  <p className="text-[11px] leading-normal text-slate-500 font-semibold">Names, Email, Phone, Location, National ID/Result Slip files, M-Pesa transaction texts.</p>
                </div>
              </div>
              <div className="p-4 bg-slate-50 border border-slate-200/60 rounded-xl flex items-start gap-3">
                <Lock className="h-5 w-5 text-[#15A24A] shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-1">Security Standard</h4>
                  <p className="text-[11px] leading-normal text-slate-500 font-semibold">Sandbox binary checking, sandboxed static asset encryption, restricted instructor access.</p>
                </div>
              </div>
            </div>

            {/* Detailed sections */}
            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-slate-950 tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">1.</span> Legal Basis and Consent
              </h3>
              <p>
                By submitting our <strong>Admission Application Form</strong> or contact channels, you provide your explicit, unambiguous, and informed consent to Dazu hub Institute to collect and process your personal and academic credentials. This consent is collected to facilitate your official course admission, academic recordkeeping, and certification processes. You have the right to withdraw your consent at any time, subject to statutory obligations related to academic enrollment records.
              </p>
            </div>

            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-[#15A24A] tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">2.</span> Specific Types of Personal Data We Collect
              </h3>
              <p>
                To coordinate cohorts, verify high-school level requirements, and register students with official certification tracks, we collect:
              </p>
              <ul className="list-disc pl-5 space-y-2 text-slate-500">
                <li><strong>Identity Information:</strong> Full Legal Names, National Identification / Passport scanned copies or certificate files.</li>
                <li><strong>Contact Information:</strong> Active Email Address, mobile Phone Numbers, and current County/Country of residence.</li>
                <li><strong>Academic Credentials:</strong> Scanned high school result slips or relevant certificates to verify baseline study requirements.</li>
                <li><strong>Payment Logs:</strong> M-Pesa transaction confirmation SMS codes and messages, to verify the non-refundable KES 1,000 admission fee.</li>
              </ul>
            </div>

            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-[#15A24A] tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">3.</span> File Upload Security Measures (Anti-Arbitrary Uploads)
              </h3>
              <p>
                To defend against hackers and prevent malicious cyber risks (such as shell files, executable script injection, or remote access Trojans), Dazu hub Institute implements a multi-tier sandboxed security filter on our forms:
              </p>
              <ul className="list-disc pl-5 space-y-2 text-slate-500">
                <li><strong>MIME & Signature Verification:</strong> We strictly filter binary streams. Only valid, standardized document formats (specifically `.pdf`, `.jpg`, `.jpeg`, and `.png`) are allowed.</li>
                <li><strong>Extension Lockdown:</strong> Automated scripts checking file headers block attempts to execute arbitrary shell scripts (e.g. `.php`, `.sh`, `.exe`, `.js`).</li>
                <li><strong>Size Limits:</strong> Maximum file thresholds are limited to 5MB to optimize server-side storage and parsing times.</li>
              </ul>
            </div>

            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-[#15A24A] tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">4.</span> Purpose of Data Processing
              </h3>
              <p>
                Your captured data is handled exclusively for the following academic activities:
              </p>
              <ul className="list-disc pl-5 space-y-2 text-slate-500">
                <li>Verifying admissions criteria and issuing student registration numbers.</li>
                <li>Generating and emailing course intake status, receipts, and study syllabi.</li>
                <li>Enabling administrative check-ins and managing secure login profiles for active course dashboards.</li>
                <li>Liaising with corporate internship partners in Nairobi and Thika to support post-study placements.</li>
              </ul>
            </div>

            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-[#15A24A] tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">5.</span> Security of Your Data
              </h3>
              <p>
                Dazu hub Institute employs advanced security techniques (including TLS 1.3 encryption on transit, separate sandboxed storage volumes, and masked database entries). Administrative access is tightly controlled—no anonymous visitor can access or download student files or view back-office applicant indices. Access is limited solely to verified instructors and registrars using multi-factor credentials.
              </p>
            </div>

            <div className="space-y-4">
              <h3 className="text-base font-extrabold text-[#15A24A] tracking-tight border-b border-slate-100 pb-2 flex items-center gap-1.5">
                <span className="text-[#15A24A]">6.</span> Your Rights under the Kenya Data Protection Act
              </h3>
              <p>
                As a data subject in Kenya, you have the following inalienable rights:
              </p>
              <ul className="list-disc pl-5 space-y-2 text-slate-500">
                <li><strong>Right of Access:</strong> You can request a digital transcript copy of all your application files.</li>
                <li><strong>Right to Rectification:</strong> You can update wrong phone numbers, emails, or misspelled names.</li>
                <li><strong>Right to Erasure / Right to be Forgotten:</strong> You can request the removal of draft documents or canceled applications, provided no ongoing enrollment records remain.</li>
                <li><strong>Right to Lodge Complaints:</strong> You may raise concerns regarding data usage with the ODPC (Kenya).</li>
              </ul>
              <p className="pt-2">
                For any data questions, request copies, or correction requests, reach our designated Data Compliance Team at: <strong>info@dazuaihub.com</strong>.
              </p>
            </div>

          </div>

          {/* Action button */}
          <div className="pt-8 border-t border-slate-100 text-center">
            <button
              onClick={() => navigate('/')}
              className="px-6 py-3 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-bold uppercase tracking-wider rounded-xl transition-all shadow-sm cursor-pointer"
            >
              I Understand & Acknowledge
            </button>
          </div>

        </div>

      </div>
    </div>
  );
}
