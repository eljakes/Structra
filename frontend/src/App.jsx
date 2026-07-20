import { useEffect, useMemo, useState } from 'react'
import {
  AlertTriangle,
  BarChart3,
  Building2,
  CalendarDays,
  CheckCircle2,
  ClipboardList,
  FileText,
  FolderKanban,
  Layers3,
  LogOut,
  Plus,
  RefreshCcw,
  Send,
  Settings,
  ShieldCheck,
  Truck,
  Upload,
  Users,
  WalletCards,
} from 'lucide-react'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { api, getToken, setToken, validationSummary } from './lib/api'
import './App.css'

const navItems = [
  { id: 'dashboard', label: 'Dashboard', icon: BarChart3 },
  { id: 'projects', label: 'Projects', icon: FolderKanban },
  { id: 'procurement', label: 'Procurement', icon: Truck },
  { id: 'documents', label: 'Documents', icon: FileText },
  { id: 'reports', label: 'Reports', icon: ClipboardList },
  { id: 'admin', label: 'Admin', icon: Settings },
]

const currencyFormatter = new Intl.NumberFormat('en-GH', {
  style: 'currency',
  currency: 'GHS',
  maximumFractionDigits: 0,
})

const compactFormatter = new Intl.NumberFormat('en-GH', {
  notation: 'compact',
  maximumFractionDigits: 1,
})

const healthColors = {
  on_track: '#188a5a',
  at_risk: '#c47a16',
  critical: '#c24132',
}

const statusColor = {
  active: 'good',
  approved: 'good',
  approved_for_construction: 'good',
  on_track: 'good',
  submitted: 'warn',
  issued: 'warn',
  issued_for_review: 'warn',
  at_risk: 'warn',
  blocked: 'bad',
  critical: 'bad',
  rejected: 'bad',
  cancelled: 'bad',
  draft: 'neutral',
  planning: 'neutral',
}

const emptyProjectForm = {
  branch_id: '',
  client_name: '',
  name: '',
  status: 'active',
  contract_value: '',
  start_date: '',
  target_end_date: '',
  site_address: '',
}

const emptyTaskForm = {
  title: '',
  status: 'todo',
  priority: 'normal',
  progress_percent: 0,
  due_date: '',
}

const emptyBudgetForm = {
  cost_code: '',
  description: '',
  category: 'materials',
  budget_amount: '',
}

const emptyReqForm = {
  title: '',
  priority: 'normal',
  required_by: '',
  supplier_id: '',
  description: '',
  cost_code: '',
  quantity: 1,
  unit: 'each',
  estimated_unit_cost: '',
}

function App() {
  const [tokenReady, setTokenReady] = useState(Boolean(getToken()))
  const [authMode, setAuthMode] = useState('login')
  const [authForm, setAuthForm] = useState({
    company_name: '',
    branch_name: 'Head Office',
    country: 'GH',
    currency: 'GHS',
    name: '',
    email: 'owner@structra.test',
    password: 'Structra2026',
  })
  const [activeView, setActiveView] = useState('dashboard')
  const [user, setUser] = useState(null)
  const [dashboard, setDashboard] = useState(null)
  const [organization, setOrganization] = useState(null)
  const [projects, setProjects] = useState([])
  const [selectedProjectId, setSelectedProjectId] = useState(null)
  const [selectedProject, setSelectedProject] = useState(null)
  const [requisitions, setRequisitions] = useState([])
  const [purchaseOrders, setPurchaseOrders] = useState([])
  const [documents, setDocuments] = useState([])
  const [drawings, setDrawings] = useState([])
  const [reports, setReports] = useState(null)
  const [loading, setLoading] = useState(false)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')

  const [projectForm, setProjectForm] = useState(emptyProjectForm)
  const [taskForm, setTaskForm] = useState(emptyTaskForm)
  const [budgetForm, setBudgetForm] = useState(emptyBudgetForm)
  const [reqForm, setReqForm] = useState(emptyReqForm)
  const [documentForm, setDocumentForm] = useState({})
  const [drawingForm, setDrawingForm] = useState({})
  const [revisionForm, setRevisionForm] = useState({ drawing_id: '', revision_code: '', notes: '' })
  const [adminForms, setAdminForms] = useState({
    company: {},
    branch: { code: '', name: '', city: '', country: 'GH' },
    client: { name: '', contact_name: '', email: '', phone: '' },
    supplier: { name: '', contact_name: '', email: '', phone: '', rating: 4, lead_time_days: 7 },
    user: { name: '', email: '', password: 'Structra2026', branch_id: '', role_id: '', job_title: '' },
  })

  const branches = organization?.company?.branches || []
  const suppliers = organization?.suppliers || []
  const clients = organization?.clients || []
  const roles = organization?.company?.roles || []
  const users = organization?.company?.users || []
  const firstBranchId = branches[0]?.id || ''
  const firstRoleId = roles[0]?.id || ''

  useEffect(() => {
    if (tokenReady) {
      refreshWorkspace()
    }
  }, [tokenReady])

  useEffect(() => {
    if (!selectedProjectId && projects[0]?.id) {
      setSelectedProjectId(projects[0].id)
    }
  }, [projects, selectedProjectId])

  useEffect(() => {
    if (selectedProjectId && tokenReady) {
      refreshProject(selectedProjectId)
    }
  }, [selectedProjectId, tokenReady])

  useEffect(() => {
    if (firstBranchId && !projectForm.branch_id) {
      setProjectForm((current) => ({ ...current, branch_id: firstBranchId }))
    }

    if (firstBranchId && !adminForms.user.branch_id) {
      setAdminForms((current) => ({
        ...current,
        user: { ...current.user, branch_id: firstBranchId },
      }))
    }

    if (firstRoleId && !adminForms.user.role_id) {
      setAdminForms((current) => ({
        ...current,
        user: { ...current.user, role_id: firstRoleId },
      }))
    }
  }, [firstBranchId, firstRoleId, projectForm.branch_id, adminForms.user.branch_id, adminForms.user.role_id])

  async function refreshWorkspace() {
    setLoading(true)
    setError('')

    try {
      const [me, dashboardData, orgData, projectData, reqData, poData, docData, drawingData, reportData] =
        await Promise.all([
          api.me(),
          api.dashboard(),
          api.organization(),
          api.projects(),
          api.requisitions(),
          api.purchaseOrders(),
          api.documents(),
          api.drawings(),
          api.reports(),
        ])

      setUser(me.user)
      setDashboard(dashboardData)
      setOrganization(orgData)
      setProjects(projectData.data || [])
      setRequisitions(reqData.data || [])
      setPurchaseOrders(poData.data || [])
      setDocuments(docData.data || [])
      setDrawings(drawingData.data || [])
      setReports(reportData)
      setAdminForms((current) => ({
        ...current,
        company: {
          name: orgData.company?.name || '',
          registration_number: orgData.company?.registration_number || '',
          tax_id: orgData.company?.tax_id || '',
          default_currency: orgData.company?.default_currency || 'GHS',
          country: orgData.company?.country || 'GH',
          base_timezone: orgData.company?.base_timezone || 'Africa/Accra',
        },
      }))
    } catch (err) {
      if (err.status === 401) {
        setToken(null)
        setTokenReady(false)
      }

      setError(validationSummary(err))
    } finally {
      setLoading(false)
    }
  }

  async function refreshProject(projectId = selectedProjectId) {
    if (!projectId) return

    try {
      const payload = await api.project(projectId)
      setSelectedProject(payload.project)
    } catch (err) {
      setError(validationSummary(err))
    }
  }

  async function runAction(action, successMessage, options = {}) {
    setError('')
    setNotice('')

    try {
      const result = await action()
      setNotice(successMessage)

      if (options.skipRefresh) {
        return result
      }

      if (options.refreshProjectOnly) {
        await refreshProject()
      } else {
        await refreshWorkspace()
      }

      return result
    } catch (err) {
      setError(validationSummary(err))
      return null
    }
  }

  async function handleAuth(event) {
    event.preventDefault()
    setError('')
    setLoading(true)

    try {
      const payload =
        authMode === 'login'
          ? await api.login({ email: authForm.email, password: authForm.password })
          : await api.register(authForm)

      setToken(payload.token)
      setUser(payload.user)
      setTokenReady(true)
      setNotice('Signed in.')
    } catch (err) {
      setError(validationSummary(err))
    } finally {
      setLoading(false)
    }
  }

  async function handleLogout() {
    await runAction(() => api.logout(), 'Signed out.', { skipRefresh: true })
    setToken(null)
    setTokenReady(false)
    setUser(null)
  }

  function setAdminFormValue(section) {
    return (event) => {
      const { name, value } = event.target
      setAdminForms((current) => ({
        ...current,
        [section]: { ...current[section], [name]: value },
      }))
    }
  }

  async function createProject(event) {
    event.preventDefault()
    const result = await runAction(
      () =>
        api.createProject({
          ...projectForm,
          branch_id: Number(projectForm.branch_id),
          contract_value: Number(projectForm.contract_value || 0),
        }),
      'Project created.',
    )

    if (result?.project?.id) {
      setSelectedProjectId(result.project.id)
      setProjectForm({ ...emptyProjectForm, branch_id: projectForm.branch_id })
    }
  }

  async function createTask(event) {
    event.preventDefault()
    if (!selectedProject) return

    await runAction(
      () =>
        api.createTask(selectedProject.id, {
          ...taskForm,
          progress_percent: Number(taskForm.progress_percent || 0),
        }),
      'Task added.',
    )
    setTaskForm(emptyTaskForm)
  }

  async function createBudgetLine(event) {
    event.preventDefault()
    if (!selectedProject) return

    await runAction(
      () =>
        api.createBudgetLine(selectedProject.id, {
          ...budgetForm,
          budget_amount: Number(budgetForm.budget_amount || 0),
        }),
      'Budget line added.',
    )
    setBudgetForm(emptyBudgetForm)
  }

  async function createRequisition(event) {
    event.preventDefault()
    if (!selectedProject) return

    await runAction(
      () =>
        api.createRequisition(selectedProject.id, {
          title: reqForm.title,
          priority: reqForm.priority,
          required_by: reqForm.required_by || null,
          lines: [
            {
              supplier_id: reqForm.supplier_id ? Number(reqForm.supplier_id) : null,
              description: reqForm.description,
              cost_code: reqForm.cost_code,
              quantity: Number(reqForm.quantity || 1),
              unit: reqForm.unit || 'each',
              estimated_unit_cost: Number(reqForm.estimated_unit_cost || 0),
            },
          ],
        }),
      'Requisition created.',
    )
    setReqForm({ ...emptyReqForm, supplier_id: reqForm.supplier_id })
  }

  async function uploadDocument(event) {
    event.preventDefault()

    const formData = new FormData()
    Object.entries(documentForm).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') formData.append(key, value)
    })

    if (!formData.get('branch_id') && user?.branch?.id) formData.append('branch_id', user.branch.id)

    await runAction(() => api.uploadDocument(formData), 'Document uploaded.')
    setDocumentForm({})
    event.target.reset()
  }

  async function uploadDrawing(event) {
    event.preventDefault()

    const formData = new FormData()
    Object.entries(drawingForm).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') formData.append(key, value)
    })

    if (!formData.get('branch_id') && user?.branch?.id) formData.append('branch_id', user.branch.id)

    await runAction(() => api.uploadDrawing(formData), 'Drawing uploaded.')
    setDrawingForm({})
    event.target.reset()
  }

  async function reviseDrawing(event) {
    event.preventDefault()
    if (!revisionForm.drawing_id) return

    const formData = new FormData()
    Object.entries(revisionForm).forEach(([key, value]) => {
      if (key !== 'drawing_id' && value !== undefined && value !== null && value !== '') {
        formData.append(key, value)
      }
    })

    await runAction(() => api.reviseDrawing(revisionForm.drawing_id, formData), 'Drawing revision issued.')
    setRevisionForm({ drawing_id: '', revision_code: '', notes: '' })
    event.target.reset()
  }

  const selectedProjectRequisitions = useMemo(
    () => requisitions.filter((item) => item.project_id === selectedProject?.id),
    [requisitions, selectedProject],
  )

  const selectedProjectOrders = useMemo(
    () => purchaseOrders.filter((item) => item.project_id === selectedProject?.id),
    [purchaseOrders, selectedProject],
  )

  if (!tokenReady) {
    return (
      <AuthScreen
        authMode={authMode}
        setAuthMode={setAuthMode}
        authForm={authForm}
        setAuthForm={setAuthForm}
        handleAuth={handleAuth}
        loading={loading}
        error={error}
      />
    )
  }

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand-block">
          <div className="brand-mark">S</div>
          <div>
            <strong>Structra</strong>
            <span>Construction OS</span>
          </div>
        </div>

        <nav className="nav-list" aria-label="Primary">
          {navItems.map((item) => {
            const Icon = item.icon
            return (
              <button
                type="button"
                key={item.id}
                className={activeView === item.id ? 'active' : ''}
                onClick={() => setActiveView(item.id)}
                title={item.label}
              >
                <Icon size={18} />
                <span>{item.label}</span>
              </button>
            )
          })}
        </nav>

        <div className="sidebar-footer">
          <div className="user-mini">
            <span>{initials(user?.name)}</span>
            <div>
              <strong>{user?.name}</strong>
              <small>{user?.role?.name}</small>
            </div>
          </div>
        </div>
      </aside>

      <main className="workspace">
        <header className="topbar">
          <div>
            <p>{user?.company?.name}</p>
            <h1>{navItems.find((item) => item.id === activeView)?.label}</h1>
          </div>
          <div className="topbar-actions">
            <button type="button" className="icon-button" onClick={refreshWorkspace} title="Refresh">
              <RefreshCcw size={17} />
            </button>
            <button type="button" className="icon-button" onClick={handleLogout} title="Sign out">
              <LogOut size={17} />
            </button>
          </div>
        </header>

        {(notice || error || loading) && (
          <div className="system-strip">
            {loading && <span>Working...</span>}
            {notice && <span className="success">{notice}</span>}
            {error && <span className="danger">{error}</span>}
          </div>
        )}

        {activeView === 'dashboard' && <DashboardView dashboard={dashboard} projects={projects} />}
        {activeView === 'projects' && (
          <ProjectsView
            branches={branches}
            projects={projects}
            selectedProject={selectedProject}
            selectedProjectId={selectedProjectId}
            setSelectedProjectId={setSelectedProjectId}
            projectForm={projectForm}
            setProjectForm={setProjectForm}
            taskForm={taskForm}
            setTaskForm={setTaskForm}
            budgetForm={budgetForm}
            setBudgetForm={setBudgetForm}
            createProject={createProject}
            createTask={createTask}
            createBudgetLine={createBudgetLine}
            runAction={runAction}
          />
        )}
        {activeView === 'procurement' && (
          <ProcurementView
            selectedProject={selectedProject}
            projects={projects}
            suppliers={suppliers}
            reqForm={reqForm}
            setReqForm={setReqForm}
            createRequisition={createRequisition}
            requisitions={requisitions}
            purchaseOrders={purchaseOrders}
            selectedProjectRequisitions={selectedProjectRequisitions}
            selectedProjectOrders={selectedProjectOrders}
            runAction={runAction}
          />
        )}
        {activeView === 'documents' && (
          <DocumentsView
            branches={branches}
            projects={projects}
            drawings={drawings}
            documents={documents}
            documentForm={documentForm}
            setDocumentForm={setDocumentForm}
            drawingForm={drawingForm}
            setDrawingForm={setDrawingForm}
            revisionForm={revisionForm}
            setRevisionForm={setRevisionForm}
            uploadDocument={uploadDocument}
            uploadDrawing={uploadDrawing}
            reviseDrawing={reviseDrawing}
            runAction={runAction}
          />
        )}
        {activeView === 'reports' && <ReportsView reports={reports} dashboard={dashboard} />}
        {activeView === 'admin' && (
          <AdminView
            organization={organization}
            branches={branches}
            clients={clients}
            suppliers={suppliers}
            roles={roles}
            users={users}
            forms={adminForms}
            setForms={setAdminForms}
            setAdminFormValue={setAdminFormValue}
            runAction={runAction}
          />
        )}
      </main>
    </div>
  )
}

function AuthScreen({ authMode, setAuthMode, authForm, setAuthForm, handleAuth, loading, error }) {
  const register = authMode === 'register'

  return (
    <main className="auth-layout">
      <section className="auth-panel">
        <div className="brand-block auth-brand">
          <div className="brand-mark">S</div>
          <div>
            <strong>Structra</strong>
            <span>Construction Operating System</span>
          </div>
        </div>
        <form onSubmit={handleAuth} className="auth-form">
          <div className="form-header">
            <h1>{register ? 'Create workspace' : 'Sign in'}</h1>
            <div className="segmented">
              <button type="button" className={!register ? 'active' : ''} onClick={() => setAuthMode('login')}>
                Sign in
              </button>
              <button type="button" className={register ? 'active' : ''} onClick={() => setAuthMode('register')}>
                Register
              </button>
            </div>
          </div>

          {register && (
            <div className="form-grid two">
              <Field label="Company" name="company_name" value={authForm.company_name} onChange={setForm(setAuthForm)} required />
              <Field label="Branch" name="branch_name" value={authForm.branch_name} onChange={setForm(setAuthForm)} />
              <Field label="Country" name="country" value={authForm.country} onChange={setForm(setAuthForm)} required maxLength={2} />
              <Field label="Currency" name="currency" value={authForm.currency} onChange={setForm(setAuthForm)} required maxLength={3} />
              <Field label="Name" name="name" value={authForm.name} onChange={setForm(setAuthForm)} required />
            </div>
          )}

          <Field label="Email" type="email" name="email" value={authForm.email} onChange={setForm(setAuthForm)} required />
          <Field label="Password" type="password" name="password" value={authForm.password} onChange={setForm(setAuthForm)} required />

          {error && <p className="form-error">{error}</p>}

          <button type="submit" className="primary-action" disabled={loading}>
            <ShieldCheck size={18} />
            {loading ? 'Working...' : register ? 'Create account' : 'Sign in'}
          </button>
        </form>
      </section>
    </main>
  )
}

function DashboardView({ dashboard, projects }) {
  const kpis = dashboard?.kpis || {}
  const costData = dashboard?.cost_by_category || []
  const healthData = (dashboard?.project_health || []).map((item) => ({
    name: labelize(item.health_status),
    value: Number(item.total),
    key: item.health_status,
  }))

  return (
    <section className="view-stack">
      <div className="kpi-grid">
        <Kpi icon={FolderKanban} label="Active projects" value={kpis.active_projects || 0} sub={`${kpis.total_projects || 0} total`} />
        <Kpi icon={WalletCards} label="Budget" value={money(kpis.budget_total)} sub={`${money(kpis.actual_cost)} actual`} />
        <Kpi icon={Truck} label="Issued PO value" value={money(kpis.issued_po_value)} sub={`${kpis.pending_approvals || 0} approvals`} />
        <Kpi icon={AlertTriangle} label="Late tasks" value={kpis.late_tasks || 0} sub={`${kpis.critical_projects || 0} critical projects`} />
      </div>

      <div className="grid-main">
        <section className="panel chart-panel">
          <PanelTitle icon={BarChart3} title="Cost By Category" />
          <ResponsiveContainer width="100%" height={280}>
            <BarChart data={costData}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="category" tickFormatter={labelize} />
              <YAxis tickFormatter={(value) => compactFormatter.format(value)} />
              <Tooltip formatter={(value) => money(value)} labelFormatter={labelize} />
              <Bar dataKey="budget" fill="#2c6d8f" radius={[4, 4, 0, 0]} />
              <Bar dataKey="committed" fill="#c47a16" radius={[4, 4, 0, 0]} />
              <Bar dataKey="actual" fill="#188a5a" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </section>

        <section className="panel chart-panel">
          <PanelTitle icon={ShieldCheck} title="Project Health" />
          <ResponsiveContainer width="100%" height={280}>
            <PieChart>
              <Pie data={healthData} dataKey="value" nameKey="name" innerRadius={62} outerRadius={102} paddingAngle={3}>
                {healthData.map((entry) => (
                  <Cell key={entry.key} fill={healthColors[entry.key] || '#6b7280'} />
                ))}
              </Pie>
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
          <div className="legend-row">
            {healthData.map((item) => (
              <span key={item.key}>
                <i style={{ background: healthColors[item.key] || '#6b7280' }} />
                {item.name}
              </span>
            ))}
          </div>
        </section>
      </div>

      <section className="panel">
        <PanelTitle icon={CalendarDays} title="Portfolio" />
        <DataTable
          columns={['Code', 'Project', 'Status', 'Health', 'Progress', 'Budget', 'Actual']}
          rows={projects.map((project) => [
            project.code,
            project.name,
            <Badge key="status" value={project.status} />,
            <Badge key="health" value={project.health_status} />,
            `${project.progress_percent}%`,
            money(project.budget_total),
            money(project.actual_cost),
          ])}
        />
      </section>
    </section>
  )
}

function ProjectsView({
  branches,
  projects,
  selectedProject,
  selectedProjectId,
  setSelectedProjectId,
  projectForm,
  setProjectForm,
  taskForm,
  setTaskForm,
  budgetForm,
  setBudgetForm,
  createProject,
  createTask,
  createBudgetLine,
  runAction,
}) {
  return (
    <section className="view-stack">
      <div className="split-layout">
        <section className="panel">
          <PanelTitle icon={FolderKanban} title="Project Register" />
          <div className="record-list">
            {projects.map((project) => (
              <button
                type="button"
                key={project.id}
                className={selectedProjectId === project.id ? 'record active' : 'record'}
                onClick={() => setSelectedProjectId(project.id)}
              >
                <strong>{project.name}</strong>
                <span>{project.code}</span>
                <Badge value={project.health_status} />
              </button>
            ))}
          </div>
        </section>

        <section className="panel">
          <PanelTitle icon={Plus} title="New Project" />
          <form className="form-grid two" onSubmit={createProject}>
            <Select label="Branch" name="branch_id" value={projectForm.branch_id} onChange={setForm(setProjectForm)} required>
              {branches.map((branch) => (
                <option value={branch.id} key={branch.id}>
                  {branch.name}
                </option>
              ))}
            </Select>
            <Field label="Client" name="client_name" value={projectForm.client_name} onChange={setForm(setProjectForm)} />
            <Field label="Project name" name="name" value={projectForm.name} onChange={setForm(setProjectForm)} required />
            <Field label="Contract value" type="number" name="contract_value" value={projectForm.contract_value} onChange={setForm(setProjectForm)} />
            <Field label="Start" type="date" name="start_date" value={projectForm.start_date} onChange={setForm(setProjectForm)} />
            <Field label="Target end" type="date" name="target_end_date" value={projectForm.target_end_date} onChange={setForm(setProjectForm)} />
            <Field className="span-2" label="Site address" name="site_address" value={projectForm.site_address} onChange={setForm(setProjectForm)} />
            <button type="submit" className="primary-action span-2">
              <Plus size={17} />
              Create project
            </button>
          </form>
        </section>
      </div>

      {selectedProject && (
        <section className="project-workspace">
          <div className="project-head">
            <div>
              <p>{selectedProject.code}</p>
              <h2>{selectedProject.name}</h2>
            </div>
            <div className="project-metrics">
              <Metric label="Progress" value={`${selectedProject.progress_percent}%`} />
              <Metric label="Budget" value={money(selectedProject.budget_total)} />
              <Metric label="Actual" value={money(selectedProject.actual_cost)} />
              <Metric label="Variance" value={money(Number(selectedProject.budget_total) - Number(selectedProject.actual_cost))} />
            </div>
          </div>

          <div className="grid-main">
            <section className="panel">
              <PanelTitle icon={CheckCircle2} title="Tasks" />
              <form className="inline-form" onSubmit={createTask}>
                <Field label="Task" name="title" value={taskForm.title} onChange={setForm(setTaskForm)} required />
                <Select label="Status" name="status" value={taskForm.status} onChange={setForm(setTaskForm)}>
                  <option value="todo">Todo</option>
                  <option value="in_progress">In progress</option>
                  <option value="blocked">Blocked</option>
                  <option value="done">Done</option>
                </Select>
                <Select label="Priority" name="priority" value={taskForm.priority} onChange={setForm(setTaskForm)}>
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </Select>
                <Field label="Due" type="date" name="due_date" value={taskForm.due_date} onChange={setForm(setTaskForm)} />
                <button type="submit" className="icon-button solid" title="Add task">
                  <Plus size={17} />
                </button>
              </form>
              <DataTable
                columns={['Task', 'Status', 'Priority', 'Progress', 'Due', '']}
                rows={(selectedProject.tasks || []).map((task) => [
                  task.title,
                  <Badge key="status" value={task.status} />,
                  <Badge key="priority" value={task.priority} />,
                  `${task.progress_percent}%`,
                  shortDate(task.due_date),
                  task.status !== 'done' ? (
                    <button
                      key="complete"
                      type="button"
                      className="table-action"
                      onClick={() =>
                        runAction(
                          () => api.updateTask(selectedProject.id, task.id, { status: 'done' }),
                          'Task completed.',
                        )
                      }
                    >
                      Done
                    </button>
                  ) : (
                    ''
                  ),
                ])}
              />
            </section>

            <section className="panel">
              <PanelTitle icon={WalletCards} title="Budget Lines" />
              <form className="inline-form" onSubmit={createBudgetLine}>
                <Field label="Code" name="cost_code" value={budgetForm.cost_code} onChange={setForm(setBudgetForm)} required />
                <Field label="Description" name="description" value={budgetForm.description} onChange={setForm(setBudgetForm)} required />
                <Select label="Category" name="category" value={budgetForm.category} onChange={setForm(setBudgetForm)}>
                  <option value="materials">Materials</option>
                  <option value="labour">Labour</option>
                  <option value="equipment">Equipment</option>
                  <option value="subcontractor">Subcontractor</option>
                  <option value="overheads">Overheads</option>
                </Select>
                <Field label="Budget" type="number" name="budget_amount" value={budgetForm.budget_amount} onChange={setForm(setBudgetForm)} required />
                <button type="submit" className="icon-button solid" title="Add budget line">
                  <Plus size={17} />
                </button>
              </form>
              <DataTable
                columns={['Code', 'Description', 'Category', 'Budget', 'Committed', 'Actual', 'Forecast']}
                rows={(selectedProject.budget_lines || []).map((line) => [
                  line.cost_code,
                  line.description,
                  labelize(line.category),
                  money(line.budget_amount),
                  money(line.committed_amount),
                  money(line.actual_amount),
                  money(line.forecast_amount),
                ])}
              />
            </section>
          </div>
        </section>
      )}
    </section>
  )
}

function ProcurementView({
  selectedProject,
  suppliers,
  reqForm,
  setReqForm,
  createRequisition,
  requisitions,
  purchaseOrders,
  selectedProjectRequisitions,
  selectedProjectOrders,
  runAction,
}) {
  const visibleRequisitions = selectedProject ? selectedProjectRequisitions : requisitions
  const visibleOrders = selectedProject ? selectedProjectOrders : purchaseOrders

  return (
    <section className="view-stack">
      <section className="panel">
        <PanelTitle icon={Send} title="Material Request" />
        <form className="form-grid procurement-form" onSubmit={createRequisition}>
          <Field label="Title" name="title" value={reqForm.title} onChange={setForm(setReqForm)} required />
          <Select label="Supplier" name="supplier_id" value={reqForm.supplier_id} onChange={setForm(setReqForm)}>
            <option value="">Unassigned</option>
            {suppliers.map((supplier) => (
              <option value={supplier.id} key={supplier.id}>
                {supplier.name}
              </option>
            ))}
          </Select>
          <Select label="Priority" name="priority" value={reqForm.priority} onChange={setForm(setReqForm)}>
            <option value="normal">Normal</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
          </Select>
          <Field label="Required by" type="date" name="required_by" value={reqForm.required_by} onChange={setForm(setReqForm)} />
          <Field label="Line item" name="description" value={reqForm.description} onChange={setForm(setReqForm)} required />
          <Field label="Cost code" name="cost_code" value={reqForm.cost_code} onChange={setForm(setReqForm)} />
          <Field label="Qty" type="number" name="quantity" value={reqForm.quantity} onChange={setForm(setReqForm)} required />
          <Field label="Unit" name="unit" value={reqForm.unit} onChange={setForm(setReqForm)} />
          <Field label="Unit cost" type="number" name="estimated_unit_cost" value={reqForm.estimated_unit_cost} onChange={setForm(setReqForm)} required />
          <button type="submit" className="primary-action">
            <Send size={17} />
            Create requisition
          </button>
        </form>
      </section>

      <div className="grid-main">
        <section className="panel">
          <PanelTitle icon={ClipboardList} title="Requisitions" />
          <DataTable
            columns={['No.', 'Project', 'Status', 'Value', 'Required', 'Actions']}
            rows={visibleRequisitions.map((item) => [
              item.requisition_number,
              item.project?.name || '',
              <Badge key="status" value={item.status} />,
              money(item.total_estimated),
              shortDate(item.required_by),
              <WorkflowButtons
                key="workflow"
                item={item}
                suppliers={suppliers}
                runAction={runAction}
              />,
            ])}
          />
        </section>

        <section className="panel">
          <PanelTitle icon={Truck} title="Purchase Orders" />
          <DataTable
            columns={['No.', 'Supplier', 'Status', 'Delivery', 'Value', 'Next']}
            rows={visibleOrders.map((order) => [
              order.po_number,
              order.supplier?.name,
              <Badge key="status" value={order.status} />,
              <Badge key="delivery" value={order.delivery_status} />,
              money(order.total_amount),
              nextPoStatus(order.status) ? (
                <button
                  key="transition"
                  type="button"
                  className="table-action"
                  onClick={() =>
                    runAction(
                      () => api.transitionPurchaseOrder(order.id, nextPoStatus(order.status)),
                      `PO moved to ${labelize(nextPoStatus(order.status))}.`,
                    )
                  }
                >
                  {labelize(nextPoStatus(order.status))}
                </button>
              ) : (
                ''
              ),
            ])}
          />
        </section>
      </div>
    </section>
  )
}

function WorkflowButtons({ item, suppliers, runAction }) {
  const supplierId = item.lines?.[0]?.supplier_id || suppliers[0]?.id

  return (
    <div className="row-actions">
      {['draft', 'rejected'].includes(item.status) && (
        <button type="button" className="table-action" onClick={() => runAction(() => api.submitRequisition(item.id), 'Requisition submitted.')}>
          Submit
        </button>
      )}
      {item.status === 'submitted' && (
        <>
          <button type="button" className="table-action" onClick={() => runAction(() => api.reviewRequisition(item.id, 'approved'), 'Requisition approved.')}>
            Approve
          </button>
          <button type="button" className="table-action danger" onClick={() => runAction(() => api.reviewRequisition(item.id, 'rejected'), 'Requisition rejected.')}>
            Reject
          </button>
        </>
      )}
      {item.status === 'approved' && supplierId && (
        <button type="button" className="table-action" onClick={() => runAction(() => api.convertRequisition(item.id, { supplier_id: supplierId }), 'Purchase order created.')}>
          Convert
        </button>
      )}
    </div>
  )
}

function DocumentsView({
  branches,
  projects,
  drawings,
  documents,
  documentForm,
  setDocumentForm,
  drawingForm,
  setDrawingForm,
  revisionForm,
  setRevisionForm,
  uploadDocument,
  uploadDrawing,
  reviseDrawing,
  runAction,
}) {
  return (
    <section className="view-stack">
      <div className="grid-main">
        <section className="panel">
          <PanelTitle icon={Upload} title="Upload Document" />
          <form className="form-grid two" onSubmit={uploadDocument}>
            <Field label="Title" name="title" value={documentForm.title || ''} onChange={setForm(setDocumentForm)} required />
            <Select label="Type" name="document_type" value={documentForm.document_type || 'general'} onChange={setForm(setDocumentForm)}>
              <option value="general">General</option>
              <option value="contract">Contract</option>
              <option value="invoice">Invoice</option>
              <option value="quality">Quality</option>
              <option value="safety">Safety</option>
              <option value="policy">Policy</option>
            </Select>
            <Select label="Branch" name="branch_id" value={documentForm.branch_id || ''} onChange={setForm(setDocumentForm)}>
              <option value="">Default</option>
              {branches.map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.name}
                </option>
              ))}
            </Select>
            <Select label="Project" name="project_id" value={documentForm.project_id || ''} onChange={setForm(setDocumentForm)}>
              <option value="">Branch library</option>
              {projects.map((project) => (
                <option key={project.id} value={project.id}>
                  {project.name}
                </option>
              ))}
            </Select>
            <Field label="Folder" name="folder" value={documentForm.folder || ''} onChange={setForm(setDocumentForm)} />
            <label className="field">
              <span>File</span>
              <input type="file" name="file" onChange={(event) => setDocumentForm((current) => ({ ...current, file: event.target.files[0] }))} />
            </label>
            <button type="submit" className="primary-action span-2">
              <Upload size={17} />
              Upload document
            </button>
          </form>
        </section>

        <section className="panel">
          <PanelTitle icon={Layers3} title="Upload Drawing" />
          <form className="form-grid two" onSubmit={uploadDrawing}>
            <Field label="Drawing no." name="drawing_number" value={drawingForm.drawing_number || ''} onChange={setForm(setDrawingForm)} required />
            <Field label="Title" name="title" value={drawingForm.title || ''} onChange={setForm(setDrawingForm)} required />
            <Select label="Discipline" name="discipline" value={drawingForm.discipline || 'architectural'} onChange={setForm(setDrawingForm)}>
              <option value="architectural">Architectural</option>
              <option value="structural">Structural</option>
              <option value="mep">MEP</option>
              <option value="civil">Civil</option>
              <option value="interiors">Interiors</option>
            </Select>
            <Field label="Revision" name="revision_code" value={drawingForm.revision_code || ''} onChange={setForm(setDrawingForm)} placeholder="P01" />
            <Select label="Project" name="project_id" value={drawingForm.project_id || ''} onChange={setForm(setDrawingForm)}>
              <option value="">Branch drawing library</option>
              {projects.map((project) => (
                <option key={project.id} value={project.id}>
                  {project.name}
                </option>
              ))}
            </Select>
            <label className="field">
              <span>File</span>
              <input type="file" name="file" onChange={(event) => setDrawingForm((current) => ({ ...current, file: event.target.files[0] }))} />
            </label>
            <button type="submit" className="primary-action span-2">
              <Upload size={17} />
              Upload drawing
            </button>
          </form>
        </section>
      </div>

      <section className="panel">
        <PanelTitle icon={FileText} title="Document Repository" />
        <DataTable
          columns={['No.', 'Title', 'Scope', 'Type', 'Version', 'Status']}
          rows={documents.map((doc) => [
            doc.document_number,
            doc.title,
            labelize(doc.repository_scope),
            labelize(doc.document_type),
            `v${doc.version}`,
            <Badge key="status" value={doc.status} />,
          ])}
        />
      </section>

      <section className="panel">
        <PanelTitle icon={Layers3} title="Drawing Library" />
        <form className="inline-form" onSubmit={reviseDrawing}>
          <Select label="Drawing" name="drawing_id" value={revisionForm.drawing_id} onChange={setForm(setRevisionForm)}>
            <option value="">Select drawing</option>
            {drawings.map((drawing) => (
              <option key={drawing.id} value={drawing.id}>
                {drawing.drawing_number} - {drawing.title}
              </option>
            ))}
          </Select>
          <Field label="Revision" name="revision_code" value={revisionForm.revision_code} onChange={setForm(setRevisionForm)} required />
          <Field label="Notes" name="notes" value={revisionForm.notes} onChange={setForm(setRevisionForm)} />
          <label className="field compact-file">
            <span>File</span>
            <input type="file" name="file" onChange={(event) => setRevisionForm((current) => ({ ...current, file: event.target.files[0] }))} />
          </label>
          <button type="submit" className="icon-button solid" title="Issue revision">
            <Plus size={17} />
          </button>
        </form>
        <DataTable
          columns={['No.', 'Title', 'Discipline', 'Revision', 'Status', 'Action']}
          rows={drawings.map((drawing) => [
            drawing.drawing_number,
            drawing.title,
            labelize(drawing.discipline),
            drawing.current_revision,
            <Badge key="status" value={drawing.status} />,
            drawing.status === 'issued_for_review' ? (
              <button
                key="approve"
                type="button"
                className="table-action"
                onClick={() => runAction(() => api.transitionDrawing(drawing.id, 'approved_for_construction'), 'Drawing approved.')}
              >
                Approve
              </button>
            ) : (
              ''
            ),
          ])}
        />
      </section>
    </section>
  )
}

function ReportsView({ reports, dashboard }) {
  const portfolio = reports?.portfolio || []
  const costControl = reports?.cost_control || []
  const procurement = reports?.procurement || {}

  return (
    <section className="view-stack">
      <div className="kpi-grid">
        <Kpi icon={Building2} label="Portfolio value" value={money(dashboard?.kpis?.contract_value)} sub={`${portfolio.length} projects`} />
        <Kpi icon={WalletCards} label="Cost variance" value={money(dashboard?.kpis?.variance)} sub="Budget less actual" />
        <Kpi icon={ClipboardList} label="Requisitions" value={sumBy(procurement.requisitions, 'total')} sub="All statuses" />
        <Kpi icon={Truck} label="Purchase orders" value={sumBy(procurement.purchase_orders, 'total')} sub="All statuses" />
      </div>

      <section className="panel">
        <PanelTitle icon={WalletCards} title="Cost Control Report" />
        <DataTable
          columns={['Project', 'Code', 'Category', 'Budget', 'Committed', 'Actual', 'Variance']}
          rows={costControl.map((line) => [
            line.project?.code,
            line.cost_code,
            labelize(line.category),
            money(line.budget_amount),
            money(line.committed_amount),
            money(line.actual_amount),
            money(line.variance),
          ])}
        />
      </section>

      <section className="panel">
        <PanelTitle icon={FolderKanban} title="Portfolio Report" />
        <DataTable
          columns={['Code', 'Project', 'Client', 'Status', 'Progress', 'Contract', 'Budget', 'Actual']}
          rows={portfolio.map((project) => [
            project.code,
            project.name,
            project.client?.name || '',
            <Badge key="status" value={project.status} />,
            `${project.progress_percent}%`,
            money(project.contract_value),
            money(project.budget_total),
            money(project.actual_cost),
          ])}
        />
      </section>
    </section>
  )
}

function AdminView({ organization, branches, clients, suppliers, roles, users, forms, setForms, setAdminFormValue, runAction }) {
  const company = organization?.company

  function afterSubmit(section, reset) {
    setForms((current) => ({
      ...current,
      [section]: reset,
    }))
  }

  return (
    <section className="view-stack">
      <div className="grid-main">
        <section className="panel">
          <PanelTitle icon={Building2} title="Company" />
          <form
            className="form-grid two"
            onSubmit={(event) => {
              event.preventDefault()
              runAction(() => api.updateCompany(forms.company), 'Company updated.')
            }}
          >
            <Field label="Name" name="name" value={forms.company.name || company?.name || ''} onChange={setAdminFormValue('company')} required />
            <Field label="Registration" name="registration_number" value={forms.company.registration_number || ''} onChange={setAdminFormValue('company')} />
            <Field label="Tax ID" name="tax_id" value={forms.company.tax_id || ''} onChange={setAdminFormValue('company')} />
            <Field label="Currency" name="default_currency" value={forms.company.default_currency || 'GHS'} onChange={setAdminFormValue('company')} />
            <Field label="Country" name="country" value={forms.company.country || 'GH'} onChange={setAdminFormValue('company')} />
            <Field label="Timezone" name="base_timezone" value={forms.company.base_timezone || 'Africa/Accra'} onChange={setAdminFormValue('company')} />
            <button type="submit" className="primary-action span-2">
              <CheckCircle2 size={17} />
              Save company
            </button>
          </form>
        </section>

        <section className="panel">
          <PanelTitle icon={Building2} title="Branches" />
          <form
            className="form-grid two"
            onSubmit={(event) => {
              event.preventDefault()
              runAction(() => api.createBranch(forms.branch), 'Branch created.').then(() =>
                afterSubmit('branch', { code: '', name: '', city: '', country: 'GH' }),
              )
            }}
          >
            <Field label="Code" name="code" value={forms.branch.code} onChange={setAdminFormValue('branch')} required />
            <Field label="Name" name="name" value={forms.branch.name} onChange={setAdminFormValue('branch')} required />
            <Field label="City" name="city" value={forms.branch.city} onChange={setAdminFormValue('branch')} />
            <Field label="Country" name="country" value={forms.branch.country} onChange={setAdminFormValue('branch')} />
            <button type="submit" className="primary-action span-2">
              <Plus size={17} />
              Add branch
            </button>
          </form>
          <MiniList items={branches.map((branch) => `${branch.code} - ${branch.name}`)} />
        </section>
      </div>

      <div className="grid-main">
        <section className="panel">
          <PanelTitle icon={Users} title="Clients" />
          <form
            className="form-grid two"
            onSubmit={(event) => {
              event.preventDefault()
              runAction(() => api.createClient(forms.client), 'Client created.').then(() =>
                afterSubmit('client', { name: '', contact_name: '', email: '', phone: '' }),
              )
            }}
          >
            <Field label="Name" name="name" value={forms.client.name} onChange={setAdminFormValue('client')} required />
            <Field label="Contact" name="contact_name" value={forms.client.contact_name} onChange={setAdminFormValue('client')} />
            <Field label="Email" type="email" name="email" value={forms.client.email} onChange={setAdminFormValue('client')} />
            <Field label="Phone" name="phone" value={forms.client.phone} onChange={setAdminFormValue('client')} />
            <button type="submit" className="primary-action span-2">
              <Plus size={17} />
              Add client
            </button>
          </form>
          <MiniList items={clients.map((client) => client.name)} />
        </section>

        <section className="panel">
          <PanelTitle icon={Truck} title="Suppliers" />
          <form
            className="form-grid two"
            onSubmit={(event) => {
              event.preventDefault()
              runAction(
                () =>
                  api.createSupplier({
                    ...forms.supplier,
                    rating: Number(forms.supplier.rating || 3),
                    lead_time_days: Number(forms.supplier.lead_time_days || 7),
                  }),
                'Supplier created.',
              ).then(() =>
                afterSubmit('supplier', { name: '', contact_name: '', email: '', phone: '', rating: 4, lead_time_days: 7 }),
              )
            }}
          >
            <Field label="Name" name="name" value={forms.supplier.name} onChange={setAdminFormValue('supplier')} required />
            <Field label="Contact" name="contact_name" value={forms.supplier.contact_name} onChange={setAdminFormValue('supplier')} />
            <Field label="Email" type="email" name="email" value={forms.supplier.email} onChange={setAdminFormValue('supplier')} />
            <Field label="Lead days" type="number" name="lead_time_days" value={forms.supplier.lead_time_days} onChange={setAdminFormValue('supplier')} />
            <button type="submit" className="primary-action span-2">
              <Plus size={17} />
              Add supplier
            </button>
          </form>
          <MiniList items={suppliers.map((supplier) => supplier.name)} />
        </section>
      </div>

      <section className="panel">
        <PanelTitle icon={ShieldCheck} title="Users & Roles" />
        <form
          className="form-grid user-form"
          onSubmit={(event) => {
            event.preventDefault()
            runAction(
              () =>
                api.createUser({
                  ...forms.user,
                  branch_id: Number(forms.user.branch_id),
                  role_id: Number(forms.user.role_id),
                }),
              'User invited.',
            ).then(() =>
              afterSubmit('user', { name: '', email: '', password: 'Structra2026', branch_id: branches[0]?.id || '', role_id: roles[0]?.id || '', job_title: '' }),
            )
          }}
        >
          <Field label="Name" name="name" value={forms.user.name} onChange={setAdminFormValue('user')} required />
          <Field label="Email" type="email" name="email" value={forms.user.email} onChange={setAdminFormValue('user')} required />
          <Field label="Password" type="password" name="password" value={forms.user.password} onChange={setAdminFormValue('user')} required />
          <Field label="Title" name="job_title" value={forms.user.job_title} onChange={setAdminFormValue('user')} />
          <Select label="Branch" name="branch_id" value={forms.user.branch_id} onChange={setAdminFormValue('user')}>
            {branches.map((branch) => (
              <option key={branch.id} value={branch.id}>
                {branch.name}
              </option>
            ))}
          </Select>
          <Select label="Role" name="role_id" value={forms.user.role_id} onChange={setAdminFormValue('user')}>
            {roles.map((role) => (
              <option key={role.id} value={role.id}>
                {role.name}
              </option>
            ))}
          </Select>
          <button type="submit" className="primary-action">
            <Plus size={17} />
            Add user
          </button>
        </form>
        <DataTable
          columns={['Name', 'Email', 'Role', 'Branch', 'Status']}
          rows={users.map((item) => [
            item.name,
            item.email,
            item.role?.name,
            item.branch?.name,
            <Badge key="status" value={item.status} />,
          ])}
        />
      </section>
    </section>
  )
}

function Field({ label, className = '', ...props }) {
  return (
    <label className={`field ${className}`}>
      <span>{label}</span>
      <input {...props} />
    </label>
  )
}

function Select({ label, className = '', children, ...props }) {
  return (
    <label className={`field ${className}`}>
      <span>{label}</span>
      <select {...props}>{children}</select>
    </label>
  )
}

function Kpi({ icon: Icon, label, value, sub }) {
  return (
    <section className="kpi">
      <div>
        <Icon size={19} />
        <span>{label}</span>
      </div>
      <strong>{value}</strong>
      <small>{sub}</small>
    </section>
  )
}

function PanelTitle({ icon: Icon, title }) {
  return (
    <div className="panel-title">
      <Icon size={18} />
      <h2>{title}</h2>
    </div>
  )
}

function Metric({ label, value }) {
  return (
    <div className="metric">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  )
}

function DataTable({ columns, rows }) {
  return (
    <div className="table-wrap">
      <table>
        <thead>
          <tr>
            {columns.map((column) => (
              <th key={column}>{column}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td colSpan={columns.length} className="empty-cell">
                No records
              </td>
            </tr>
          ) : (
            rows.map((row, index) => (
              <tr key={index}>
                {row.map((cell, cellIndex) => (
                  <td key={`${index}-${cellIndex}`}>{cell}</td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  )
}

function Badge({ value }) {
  const key = String(value || 'neutral')
  return <span className={`badge ${statusColor[key] || 'neutral'}`}>{labelize(key)}</span>
}

function MiniList({ items }) {
  return (
    <ul className="mini-list">
      {items.map((item) => (
        <li key={item}>{item}</li>
      ))}
    </ul>
  )
}

function setForm(setter) {
  return (event) => {
    const { name, value } = event.target
    setter((current) => ({ ...current, [name]: value }))
  }
}

function money(value) {
  return currencyFormatter.format(Number(value || 0))
}

function labelize(value = '') {
  return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function initials(name = '') {
  return name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

function shortDate(value) {
  if (!value) return ''
  return new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(value))
}

function nextPoStatus(status) {
  return {
    draft: 'issued',
    issued: 'approved',
    approved: 'delivered',
    delivered: 'closed',
  }[status]
}

function sumBy(items = [], key) {
  return items.reduce((total, item) => total + Number(item[key] || 0), 0)
}

export default App
