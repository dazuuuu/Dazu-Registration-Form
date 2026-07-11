import React, { useState } from 'react';
import { useRouter } from '../router';
import { 
  Code, 
  ExternalLink, 
  Smartphone, 
  Database, 
  Brain, 
  Sparkles, 
  Github, 
  ChevronRight, 
  Play, 
  X,
  Info
} from 'lucide-react';

interface Project {
  title: string;
  category: 'Web Apps' | 'AI & Data' | 'Mobile & UX' | 'Automation';
  description: string;
  tech: string[];
  developer: string;
  cohort: string;
  details: string;
  mockupLogs: string[];
}

export default function Projects() {
  const { navigate } = useRouter();
  const [selectedCategory, setSelectedCategory] = useState<string>('All');
  const [activeProject, setActiveProject] = useState<Project | null>(null);

  const categories = ['All', 'Web Apps', 'AI & Data', 'Mobile & UX', 'Automation'];

  const projects: Project[] = [
    {
      title: "Thika Soko Hub",
      category: "Web Apps",
      description: "A fast local e-commerce platform allowing informal agricultural traders to list products, manage inventory, and receive payments.",
      tech: ["React.js", "Tailwind CSS", "Node.js", "Express", "PostgreSQL"],
      developer: "Eunice Wangari & Team",
      cohort: "Software Dev - Q1 2026",
      details: "This full-stack application includes merchant onboarding, custom storefront routing, real-time basket updates, and integration with M-Pesa's Daraja API for immediate payment confirmations.",
      mockupLogs: [
        "> Starting Local server on port 3000...",
        "> Connecting to PostgreSQL database...",
        "> Initializing M-Pesa Webhook handlers...",
        "> Load complete: 154 local Thika merchants online.",
        "✓ Server running live at: thika-soko.co.ke"
      ]
    },
    {
      title: "MedicAI Predictor",
      category: "AI & Data",
      description: "A localized diagnostic-assisting AI model trained to parse basic patient symptoms and suggest likely diagnoses.",
      tech: ["Python", "Pandas", "Scikit-Learn", "Streamlit", "Gemini API"],
      developer: "Dennis Kipruto",
      cohort: "AI Master Class - Feb 2026",
      details: "Dennis integrated structured regional disease stats with custom Gemini API prompts to provide supportive clinical queries for rural medical workers in Kiambu county.",
      mockupLogs: [
        "> Loading symptoms classifier weights...",
        "> Model ready. Input symptoms query...",
        "> Query: 'High fever, severe joint pains, headaches'",
        "> Output: 89% probability Dengue Fever / Malaria.",
        "✓ Suggestion logged successfully."
      ]
    },
    {
      title: "Figma RealEstate UI",
      category: "Mobile & UX",
      description: "An incredibly responsive, eye-safe mobile apartment searching interface formulated for Nairobi & Thika rentals.",
      tech: ["Figma", "UI Design", "Color Theory", "Responsive Grid"],
      developer: "Mercy Mwende",
      cohort: "Graphics & UI - Q2 2026",
      details: "A comprehensive design system featuring custom styled icons, flat minimalist lists, intuitive pricing sliders, and an extremely clean mobile booking flow.",
      mockupLogs: [
        "> Loading Figma design file token...",
        "> 24 high-fidelity frames rendered in 1080px...",
        "> Style Guide: Space Grotesk Font, Primary Emerald (#16A34A)",
        "> Interactivity: Checked slide animations, click target size: 48px.",
        "✓ Responsive mobile design verified."
      ]
    },
    {
      title: "AgriWater Smart Automation",
      category: "Automation",
      description: "An automated greenhouse hydration controller triggering irrigation schedules based on soil humidity APIs.",
      tech: ["Python", "Make.com", "JSON APIs", "Cron Schedulers"],
      developer: "Mark Kamore",
      cohort: "AI Automations - Q1 2026",
      details: "Uses live climate API endpoints to check precipitation probabilities, optimizing watering intervals. Integrates automatic Telegram alarms for immediate farmer reports.",
      mockupLogs: [
        "> Initializing automated watering cron...",
        "> Fetching weather telemetry: Rain prob 12%...",
        "> Soil sensor reading: 41% (Threshold 50%)",
        "> Action: TRIGGERING hydration valves for 15 mins...",
        "✓ Automation complete. Telegram notification dispatched."
      ]
    },
    {
      title: "FinFlow Analytics Dashboard",
      category: "AI & Data",
      description: "An interactive Power BI finance tracker designed to monitor operational costs and sales loops of Thika MSMEs.",
      tech: ["Power BI", "SQL", "Excel", "Data Modeling"],
      developer: "Joseph Njuguna",
      cohort: "Data Analytics - Q1 2026",
      details: "Includes complex DAX calculations, revenue trends, dynamic filter panels, and customer cost optimization logs designed for quick business actions.",
      mockupLogs: [
        "> Importing data query source...",
        "> Calculated YoY Revenue Growth: +34.2%",
        "> Visualizing dynamic charts...",
        "> Rendering bento widgets: Net profits, operational expenditures.",
        "✓ Dashboard active."
      ]
    },
    {
      title: "SecureNet Threat Sentinel",
      category: "Automation",
      description: "A custom bash/python network auditing script designed to scan router terminals and report weak active configurations.",
      tech: ["Python", "Linux Bash", "Networking", "Cryptography"],
      developer: "Alex Kipkoech",
      cohort: "Cybersecurity - Q2 2026",
      details: "A highly defensive security tool mapping local network nodes, identifying open vulnerable ports, and logging compliance errors with immediate remediation steps.",
      mockupLogs: [
        "> Initiating security network probe...",
        "> Checking nodes: 12 local terminals detected.",
        "> ALERT: Port 21 (FTP) is exposed on Server Node 3.",
        "> Hardening configuration patch applied.",
        "✓ Audit complete: 1 warning corrected."
      ]
    }
  ];

  const filteredProjects = projects.filter((project) => {
    return selectedCategory === 'All' || project.category === selectedCategory;
  });

  return (
    <div className="bg-white min-h-screen py-16 text-slate-900">
      <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
        
        {/* Header section */}
        <div className="max-w-2xl text-left space-y-4 mb-16">
          <span className="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full w-fit">
            Student Achievements
          </span>
          <h1 className="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900">
            Student Capstone Projects
          </h1>
          <p className="text-slate-600 font-medium leading-relaxed">
            See actual products designed, coded, and deployed live by Dazu Hub graduates. Our training focuses on real-world practical delivery.
          </p>
        </div>

        {/* Category selector */}
        <div className="flex flex-wrap items-center gap-2 pb-6 mb-12 border-b border-slate-100">
          {categories.map((cat) => (
            <button
              key={cat}
              onClick={() => setSelectedCategory(cat)}
              className={`px-4 py-3 text-xs font-bold uppercase tracking-wider rounded-xl transition-all border cursor-pointer ${
                selectedCategory === cat
                  ? 'bg-green-600 text-white border-green-600 font-extrabold'
                  : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
              }`}
            >
              {cat}
            </button>
          ))}
        </div>

        {/* Projects grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {filteredProjects.map((project, idx) => (
            <div
              key={idx}
              className="bg-slate-50 border border-slate-200 rounded-2xl p-8 text-left flex flex-col justify-between h-[340px] hover:border-green-600 hover:bg-white transition-all duration-300"
            >
              <div>
                <div className="flex items-center justify-between mb-6">
                  <span className="text-[10px] font-bold text-green-700 bg-green-50 border border-green-100 rounded-full px-2.5 py-1 uppercase tracking-widest">
                    {project.category}
                  </span>
                  <span className="text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                    {project.cohort}
                  </span>
                </div>
                <h3 className="text-xl font-extrabold text-slate-900 mb-3">{project.title}</h3>
                <p className="text-slate-600 text-xs leading-relaxed font-semibold line-clamp-3 mb-4">
                  {project.description}
                </p>
              </div>

              <div>
                {/* Tech tags */}
                <div className="flex flex-wrap gap-1.5 mb-6">
                  {project.tech.map((t) => (
                    <span key={t} className="text-[10px] font-bold bg-white border border-slate-200 text-slate-600 px-2 py-0.5 rounded-md">
                      {t}
                    </span>
                  ))}
                </div>
                {/* Footer details action */}
                <div className="pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-bold">
                  <span className="text-slate-500">By {project.developer}</span>
                  <button
                    onClick={() => setActiveProject(project)}
                    className="text-green-600 hover:text-green-700 flex items-center gap-1 cursor-pointer"
                  >
                    View Sandbox <Play className="h-3 w-3 fill-green-600" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>

      </div>

      {/* INTERACTIVE SIMULATION SANDBOX OVERLAY */}
      {activeProject && (
        <div className="fixed inset-0 bg-slate-900/80 backdrop-blur-xs flex items-center justify-center z-50 p-4">
          <div className="bg-white border-2 border-slate-900 rounded-2xl w-full max-w-xl overflow-hidden text-left">
            
            {/* Header info */}
            <div className="flex items-center justify-between p-6 border-b border-slate-200 bg-slate-50">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-green-600 rounded-xl text-white">
                  <Code className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-base font-extrabold text-slate-900">{activeProject.title}</h3>
                  <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dev Sandbox Console</p>
                </div>
              </div>
              <button 
                onClick={() => setActiveProject(null)}
                className="p-1.5 hover:bg-slate-200 rounded-lg text-slate-500 cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Sandbox details */}
            <div className="p-6 md:p-8 space-y-6">
              
              <div className="space-y-1">
                <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Created By</span>
                <p className="text-sm font-extrabold text-slate-900">
                  {activeProject.developer} <span className="text-xs text-slate-500 font-semibold">({activeProject.cohort})</span>
                </p>
              </div>

              <div className="space-y-1">
                <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Project Details</span>
                <p className="text-xs text-slate-700 font-semibold leading-relaxed">
                  {activeProject.details}
                </p>
              </div>

              {/* Code console log emulator */}
              <div className="space-y-2">
                <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Interactive Console Log</span>
                <div className="bg-slate-950 rounded-xl p-4 font-mono text-[11px] text-slate-300 space-y-1 border border-slate-900">
                  <div className="flex gap-1.5 mb-2.5 border-b border-slate-900 pb-2">
                    <span className="w-2.5 h-2.5 bg-red-500 rounded-full"></span>
                    <span className="w-2.5 h-2.5 bg-yellow-500 rounded-full"></span>
                    <span className="w-2.5 h-2.5 bg-green-500 rounded-full"></span>
                    <span className="text-[9px] text-slate-600 font-bold ml-1">bash terminal</span>
                  </div>
                  {activeProject.mockupLogs.map((log, i) => (
                    <div key={i} className="leading-relaxed">
                      {log}
                    </div>
                  ))}
                </div>
              </div>

              {/* Notice info */}
              <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 flex items-start gap-2.5">
                <Info className="h-4.5 w-4.5 text-green-600 shrink-0 mt-0.5" />
                <p className="text-[10px] text-slate-500 font-semibold leading-relaxed">
                  Every Dazu Hub graduation candidate builds and delivers a custom capstone product to demonstrate technological competence before earning their certification.
                </p>
              </div>

            </div>

            {/* Bottom buttons panel */}
            <div className="p-6 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3">
              <button
                onClick={() => setActiveProject(null)}
                className="px-5 py-2.5 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 cursor-pointer"
              >
                Close Sandbox
              </button>
              <button
                onClick={() => {
                  setActiveProject(null);
                  navigate('/apply');
                }}
                className="px-5 py-2.5 text-xs font-bold text-white bg-green-600 border border-transparent rounded-xl hover:bg-green-700 cursor-pointer flex items-center gap-1"
              >
                Enroll & Build Like This <ChevronRight className="h-4 w-4" />
              </button>
            </div>

          </div>
        </div>
      )}

    </div>
  );
}
