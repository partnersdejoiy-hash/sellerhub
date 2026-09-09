// Typed API client for the DEJOIY Seller App REST endpoints.

declare global {
  interface Window {
    DSA_CONFIG?: {
      restUrl: string
      wpRestUrl: string
      nonce: string
      home: string
      user: { id: number; name: string; isAdmin: boolean }
      version: string
    }
  }
}

const cfg = () => window.DSA_CONFIG
export const appConfig = () => cfg()

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const c = cfg()
  if (!c) throw new Error('DSA_CONFIG missing — plugin shell not loaded')
  const res = await fetch(c.restUrl + path, {
    ...options,
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': c.nonce,
      ...(options.headers || {}),
    },
  })
  if (res.status === 401) {
    window.location.href = c.restUrl.replace(/\/wp-json.*/, '/wp-login.php?redirect_to=' + encodeURIComponent(location.href))
    throw new Error('Login required')
  }
  const json = await res.json().catch(() => ({}))
  if (!res.ok) {
    const msg = (json as { message?: string })?.message || `Request failed (${res.status})`
    throw new Error(msg)
  }
  return json as T
}

export const api = {
  me: () => request<MeResponse>('/me'),
  dashboard: (range: string) => request<Dashboard>(`/dashboard?range=${range}`),
  products: (params: Record<string, string | number> = {}) =>
    request<Paged<Product>>('/products?' + qs(params)),
  product: (id: number) => request<Product>(`/products/${id}`),
  createProduct: (body: Partial<Product>) => request<Product>('/products', { method: 'POST', body: JSON.stringify(body) }),
  updateProduct: (id: number, body: Partial<Product>) => request<Product>(`/products/${id}`, { method: 'POST', body: JSON.stringify(body) }),
  deleteProduct: (id: number) => request<{ deleted: boolean }>(`/products/${id}`, { method: 'DELETE' }),
  productStock: (id: number, stock: number, delta = false) =>
    request<{ id: number; stock: number | null }>(`/products/${id}/stock`, { method: 'POST', body: JSON.stringify({ stock, delta }) }),
  bulkProducts: (action: string, ids: number[], value: Record<string, unknown> = {}) =>
    request<BulkResult>('/products/bulk', { method: 'POST', body: JSON.stringify({ action, ids, value }) }),
  orders: (params: Record<string, string | number> = {}) => request<Paged<Order>>('/orders?' + qs(params)),
  orderCounts: () => request<Record<string, number>>('/orders/counts'),
  order: (id: number) => request<OrderDetail>(`/orders/${id}`),
  orderStatus: (id: number, status: string, note = '') =>
    request<{ id: number; status: string }>(`/orders/${id}/status`, { method: 'POST', body: JSON.stringify({ status, note }) }),
  orderNote: (id: number, content: string, customer = false) =>
    request<{ added: boolean }>(`/orders/${id}/notes`, { method: 'POST', body: JSON.stringify({ content, customer }) }),
  customers: (page = 1) => request<{ items: Customer[]; total: number }>(`/customers?page=${page}`),
  reviews: () => request<ReviewsResponse>('/reviews'),
  reviewReply: (id: number, content: string) =>
    request<{ replied: boolean }>(`/reviews/${id}/reply`, { method: 'POST', body: JSON.stringify({ content }) }),
  finance: () => request<Finance>('/finance'),
  notifications: () => request<NotificationsResponse>('/notifications'),
  search: (q: string) => request<{ products: Product[]; orders: Order[] }>('/search?q=' + encodeURIComponent(q)),
  store: () => request<Store>('/store'),
  saveStore: (body: Partial<Store>) => request<Store>('/store', { method: 'POST', body: JSON.stringify(body) }),
  categories: () => request<Category[]>('/categories'),
  uploadMedia: async (file: File): Promise<Media> => {
    const c = cfg()!
    const fd = new FormData()
    fd.append('file', file)
    const res = await fetch(c.restUrl + '/media', {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-WP-Nonce': c.nonce },
      body: fd,
    })
    const json = await res.json().catch(() => ({}))
    if (!res.ok) throw new Error((json as { message?: string })?.message || 'Upload failed')
    return json as Media
  },
  ai: (message: string) => request<{ reply: string }>('/ai', { method: 'POST', body: JSON.stringify({ message }) }),
}

function qs(params: Record<string, string | number>) {
  return Object.entries(params)
    .filter(([, v]) => v !== '' && v !== undefined && v !== null)
    .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
    .join('&')
}

// ── Types ──
export interface MeResponse {
  user: { id: number; name: string; email: string; avatar: string }
  isVendor: boolean
  isAdmin: boolean
  vendorId: number
  version: string
  storefrontUrl: string
}
export interface Paged<T> { items: T[]; total: number }
export interface BulkResult { updated: number; errors: { id: number; message: string }[] }
export interface Category { id: number; name: string; parent: number }
export interface Media { id: number; url: string; thumb: string }

export interface Product {
  id: number
  name: string
  status: 'publish' | 'draft' | 'pending' | 'private' | string
  type: string
  sku: string
  price: number
  regularPrice: number
  salePrice: number | null
  stock: number | null
  stockStatus: string
  managingStock?: boolean
  manageStock?: boolean
  image: string
  gallery?: string[]
  categories: { id: number; name: string }[]
  tags?: { id: number; name: string }[]
  dateCreated: string
  dateModified: string
  rating: number
  ratingCount: number
  sales: number
  featured?: boolean
  virtual?: boolean
  downloadable?: boolean
  weight?: number
  dimensions?: { length: number; width: number; height: number }
  taxClass?: string
  taxStatus?: string
  shippingClass?: number
  description?: string
  shortDescription?: string
  purchaseNote?: string
  reviewsAllowed?: boolean
  lowStockThreshold?: number
  backorders?: string
  variations?: Variation[]
  authorId?: number
  editUrl?: string
}

export interface Variation {
  id: number
  sku: string
  price: number
  regularPrice: number
  salePrice: number | null
  stock: number | null
  stockStatus: string
  image: string
  attributes: Record<string, string>
  status: string
}

export interface Order {
  id: number
  number: string
  status: string
  date: string
  total: number
  currency: string
  customer: { name: string; email: string; phone: string }
  items: { name: string; qty: number; total: number; productId: number }[]
  itemCount: number
  paymentMethod: string
}

export interface OrderDetail extends Order {
  billing: { address1: string; address2: string; city: string; state: string; zip: string; country: string }
  shipping: { address1: string; address2: string; city: string; state: string; zip: string; country: string; method: string }
  totals: { subtotal: number; shipping: number; tax: number; discount: number; total: number }
  customerNote: string
  timeline: { content: string; date: string; customer: boolean; addedBy: string }[]
  tracking: unknown[]
  itemsDetail: { id: number; productId: number; variationId: number; name: string; qty: number; total: number; tax: number; sku: string }[]
}

export interface Customer {
  name: string
  email: string
  orders: number
  spent: number
  lastOrder: string
}

export interface ReviewsResponse {
  items: {
    id: number
    product: { id: number; name: string }
    author: string
    rating: number
    content: string
    date: string
    responded: boolean
  }[]
  count: number
  unanswered: number
}

export interface Finance {
  source: string
  revenue30: number
  revenue90: number
  refund30: number
  commission30: number | null
  netEarnings30: number | null
  totalPaid: number | null
  pendingPayout: number | null
  ledger: boolean
}

export interface NotificationsResponse {
  items: { id: string; type: string; title: string; body: string; date: string | null; unread: boolean; link: string }[]
  unread: number
}

export interface Store {
  storeName: string
  description: string
  logo: string
  banner: string
  phone: string
  email: string
  address1: string
  address2: string
  city: string
  zip: string
  country: string
  state: string
  social: { facebook: string; twitter: string; instagram: string; youtube: string; linkedin: string }
}

export interface Dashboard {
  range: string
  summary: {
    revenue: number
    orders: number
    itemsSold: number
    aov: number
    refunded: number
    customers: number
    repeatCustomers: number
  }
  series: { date: string; revenue: number; orders: number }[]
  bestSellers: { id: number; name: string; image: string; qty: number; revenue: number }[]
  inventory: { totalProducts: number; lowStock: number; outOfStock: number; stockValue: number }
  attention: { key: string; label: string; link: string; count: number }[]
  prevRevenue: number
}
