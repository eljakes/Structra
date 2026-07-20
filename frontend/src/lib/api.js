const API_BASE = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1'
const TOKEN_KEY = 'structra_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
    return
  }

  localStorage.removeItem(TOKEN_KEY)
}

export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

async function request(path, options = {}) {
  const token = getToken()
  const isFormData = options.body instanceof FormData

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
  })

  const contentType = response.headers.get('content-type') || ''
  const payload = contentType.includes('application/json') ? await response.json() : null

  if (!response.ok) {
    throw new ApiError(
      payload?.message || 'Request failed',
      response.status,
      payload?.errors || {},
    )
  }

  return payload
}

export const api = {
  login: (payload) =>
    request('/auth/login', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  register: (payload) =>
    request('/auth/register', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  logout: () => request('/auth/logout', { method: 'POST' }),
  me: () => request('/auth/me'),
  dashboard: () => request('/dashboard'),
  reports: () => request('/reports'),
  organization: () => request('/organization'),
  updateCompany: (payload) =>
    request('/organization/company', {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
  createBranch: (payload) =>
    request('/organization/branches', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  createClient: (payload) =>
    request('/organization/clients', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  createSupplier: (payload) =>
    request('/organization/suppliers', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  createUser: (payload) =>
    request('/organization/users', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  projects: () => request('/projects?per_page=100'),
  project: (projectId) => request(`/projects/${projectId}`),
  createProject: (payload) =>
    request('/projects', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  updateProject: (projectId, payload) =>
    request(`/projects/${projectId}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
  createTask: (projectId, payload) =>
    request(`/projects/${projectId}/tasks`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  updateTask: (projectId, taskId, payload) =>
    request(`/projects/${projectId}/tasks/${taskId}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
  createBudgetLine: (projectId, payload) =>
    request(`/projects/${projectId}/budget-lines`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  requisitions: () => request('/procurement/requisitions?per_page=100'),
  purchaseOrders: () => request('/procurement/purchase-orders?per_page=100'),
  createRequisition: (projectId, payload) =>
    request(`/projects/${projectId}/requisitions`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  submitRequisition: (requisitionId) =>
    request(`/procurement/requisitions/${requisitionId}/submit`, {
      method: 'POST',
    }),
  reviewRequisition: (requisitionId, decision) =>
    request(`/procurement/requisitions/${requisitionId}/review`, {
      method: 'POST',
      body: JSON.stringify({ decision }),
    }),
  convertRequisition: (requisitionId, payload) =>
    request(`/procurement/requisitions/${requisitionId}/convert-to-po`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  transitionPurchaseOrder: (purchaseOrderId, status) =>
    request(`/procurement/purchase-orders/${purchaseOrderId}/transition`, {
      method: 'POST',
      body: JSON.stringify({ status }),
    }),
  documents: () => request('/documents?per_page=100'),
  uploadDocument: (formData) =>
    request('/documents', {
      method: 'POST',
      body: formData,
    }),
  drawings: () => request('/drawings?per_page=100'),
  uploadDrawing: (formData) =>
    request('/drawings', {
      method: 'POST',
      body: formData,
    }),
  reviseDrawing: (drawingId, formData) =>
    request(`/drawings/${drawingId}/revisions`, {
      method: 'POST',
      body: formData,
    }),
  transitionDrawing: (drawingId, status) =>
    request(`/drawings/${drawingId}/transition`, {
      method: 'POST',
      body: JSON.stringify({ status }),
    }),
}

export function validationSummary(error) {
  if (!(error instanceof ApiError) || !error.errors) {
    return error.message || 'Something went wrong'
  }

  const first = Object.values(error.errors)[0]
  return Array.isArray(first) ? first[0] : error.message
}
