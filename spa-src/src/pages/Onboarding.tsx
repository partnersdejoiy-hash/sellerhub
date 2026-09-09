import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, SellerIdentity, Onboarding as OnboardingData, SellerProfile, GstInfo, KycInfo, BankInfo } from '../api'
import { ErrorBox, Loading, useToast } from '../ui'

const BUSINESS_TYPES = [
  ['individual', 'Individual'],
  ['proprietorship', 'Sole Proprietorship'],
  ['partnership', 'Partnership'],
  ['llp', 'LLP'],
  ['pvt_ltd', 'Private Limited Company'],
  ['public_ltd', 'Public Limited Company'],
  ['other', 'Other'],
]

const STEPS = ['business', 'gst', 'kyc', 'bank', 'done']

export default function Onboarding() {
  const toast = useToast()
  const { data: identity, loading, error, reload } = useIdentity()
  const { data: ob } = useOnboardingData()
  const [step, setStep] = useState(0)
  const [busy, setBusy] = useState(false)

  const [profile, setProfile] = useState<Partial<SellerProfile>>({})
  const [gstChoice, setGstChoice] = useState<'yes' | 'no' | null>(null)
  const [gst, setGst] = useState<Partial<GstInfo>>({})
  const [kyc, setKyc] = useState<Partial<KycInfo>>({})
  const [bank, setBank] = useState<Partial<BankInfo>>({})

  useEffect(() => {
    if (identity) {
      setProfile(identity.profile)
      setGst(identity.gst)
      setKyc(identity.kyc)
      setBank(identity.bank)
      if (identity.gst.hasGstin) setGstChoice('yes')
      else if (identity.profile.nonGstDeclared) setGstChoice('no')
    }
  }, [identity])

  if (loading && !identity) return <Loading label="Loading your seller profile…" />
  if (error) return <ErrorBox message={error} retry={reload} />
  if (!identity) return null

  async function saveProfile() {
    setBusy(true)
    try {
      await api.saveSellerProfile(profile)
      toast.show('Business info saved ✓')
      setStep(1)
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    } finally {
      setBusy(false)
    }
  }

  async function saveGstStep() {
    setBusy(true)
    try {
      if (gstChoice === 'yes') {
        const saved = await api.saveGst({ gstin: gst.gstin, legalName: gst.legalName, businessType: profile.businessType })
        toast.show('GSTIN saved — status: ' + saved.status.replace('_', ' '))
      } else {
        await api.saveGst({ gstin: '' })
        await api.saveSellerProfile({ ...profile, nonGstDeclared: true, declaration: true })
        toast.show('Non-GST declaration saved')
      }
      setStep(2)
      reload()
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    } finally {
      setBusy(false)
    }
  }

  async function saveKycStep() {
    setBusy(true)
    try {
      const saved = await api.saveKyc(kyc)
      toast.show('KYC ' + (saved.status === 'submitted' ? 'submitted for review ✓' : 'saved'))
      setStep(3)
      reload()
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    } finally {
      setBusy(false)
    }
  }

  async function saveBankStep() {
    setBusy(true)
    try {
      const saved = await api.saveBank(bank)
      toast.show('Payout details ' + saved.status + ' ✓')
      setStep(4)
      reload()
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div style={{ maxWidth: 860, margin: '0 auto' }}>
      <div className="page-head">
        <div>
          <h1>Seller Onboarding</h1>
          <p>
            Seller ID: <strong>{identity.sellerId}</strong> (permanent) ·{' '}
            <span className={'badge ' + (identity.eligibility.status === 'eligible' ? 'badge-ok' : 'badge-warn')}>
              {identity.eligibility.status.replace('_', ' ')}
            </span>{' '}
            <span className="badge badge-brand">{identity.eligibility.sellerType === 'gst' ? 'GST Seller' : identity.eligibility.sellerType ? 'Non-GST Seller' : 'Type pending'}</span>
          </p>
        </div>
      </div>

      {ob && (
        <div className="card card-pad" style={{ marginBottom: 18 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, marginBottom: 6 }}>
            <span style={{ color: 'var(--ink-500)' }}>Profile completion</span>
            <strong>{ob.percent}%</strong>
          </div>
          <div className="progress"><div style={{ width: `${ob.percent}%` }} /></div>
        </div>
      )}

      {/* Step rail */}
      <div className="pill-row" style={{ marginBottom: 18 }}>
        {STEPS.map((s, i) => (
          <button key={s} className={'pill' + (step === i ? ' active' : '')} onClick={() => setStep(i)}>
            {i + 1}. {s === 'business' ? 'Business' : s === 'gst' ? 'GST' : s === 'kyc' ? 'KYC' : s === 'bank' ? 'Payout' : 'Finish'}
          </button>
        ))}
      </div>

      <div className="card card-pad">
        {step === 0 && (
          <>
            <h3 style={{ marginBottom: 14 }}>Business information</h3>
            <div className="form-grid">
              <div className="field">
                <label>Business type *</label>
                <select className="select" value={profile.businessType || ''} onChange={(e) => setProfile({ ...profile, businessType: e.target.value })}>
                  <option value="">Select…</option>
                  {BUSINESS_TYPES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </div>
              <div className="field">
                <label>Legal name *</label>
                <input className="input" value={profile.legalName || ''} onChange={(e) => setProfile({ ...profile, legalName: e.target.value })} placeholder="As per PAN / documents" />
              </div>
              <div className="field">
                <label>Date of birth / incorporation</label>
                <input className="input" type="date" value={profile.dob || ''} onChange={(e) => setProfile({ ...profile, dob: e.target.value })} />
              </div>
              <div className="field">
                <label>Store category</label>
                <input className="input" value={profile.storeCategory || ''} onChange={(e) => setProfile({ ...profile, storeCategory: e.target.value })} placeholder="e.g. Fashion, Electronics" />
              </div>
              <div className="field">
                <label>Support email</label>
                <input className="input" type="email" value={profile.supportEmail || ''} onChange={(e) => setProfile({ ...profile, supportEmail: e.target.value })} />
              </div>
              <div className="field">
                <label>Website / social</label>
                <input className="input" value={profile.website || ''} onChange={(e) => setProfile({ ...profile, website: e.target.value })} placeholder="https://" />
              </div>
            </div>
            <button className="btn btn-primary" disabled={busy || !profile.businessType || !profile.legalName} onClick={saveProfile}>Continue →</button>
          </>
        )}

        {step === 1 && (
          <>
            <h3 style={{ marginBottom: 6 }}>GST registration</h3>
            <p className="help" style={{ marginBottom: 14 }}>
              GST requirements depend on your business type, product categories, turnover and applicable Indian tax laws.
              DEJOIY verifies GSTIN server-side; without a verification provider, GSTIN stays <strong>“Pending verification”</strong>.
            </p>
            <div style={{ display: 'flex', gap: 10, marginBottom: 16 }}>
              <button className={'pill' + (gstChoice === 'yes' ? ' active' : '')} onClick={() => setGstChoice('yes')}>Yes, I have a GSTIN</button>
              <button className={'pill' + (gstChoice === 'no' ? ' active' : '')} onClick={() => setGstChoice('no')}>No, I don't have GSTIN</button>
            </div>
            {gstChoice === 'yes' && (
              <div className="form-grid">
                <div className="field">
                  <label>GSTIN *</label>
                  <input className="input" value={gst.gstin && gst.gstin.includes('X') ? '' : gst.gstin || ''} onChange={(e) => setGst({ ...gst, gstin: e.target.value.toUpperCase() })} placeholder="22AAAAA0000A1Z5" maxLength={15} />
                  {gst.gstin && gst.gstin.includes('X') && <div className="help">Saved GSTIN is masked. Type a new one only to change it.</div>}
                </div>
                <div className="field">
                  <label>Legal business name</label>
                  <input className="input" value={gst.legalName || ''} onChange={(e) => setGst({ ...gst, legalName: e.target.value })} />
                </div>
              </div>
            )}
            {gstChoice === 'no' && (
              <div style={{ background: 'var(--brand-50)', borderRadius: 12, padding: 16, marginBottom: 14 }}>
                <strong style={{ display: 'block', marginBottom: 6 }}>Non-GST seller eligibility</strong>
                <p style={{ fontSize: 13, color: 'var(--ink-700)' }}>
                  Non-GST selling on DEJOIY is limited to eligible categories under DEJOIY policy and applicable Indian tax rules.
                  Your selling scope may be restricted (category and volume limits). You can add a GSTIN later and your account
                  will transition automatically. This is a marketplace policy decision — not tax advice.
                </p>
                <label style={{ display: 'flex', gap: 8, alignItems: 'flex-start', marginTop: 10, fontSize: 13 }}>
                  <input type="checkbox" checked={profile.declaration || false} onChange={(e) => setProfile({ ...profile, declaration: e.target.checked })} />
                  I declare the information provided is accurate and I understand my tax compliance obligations.
                </label>
              </div>
            )}
            <button
              className="btn btn-primary"
              disabled={busy || !gstChoice || (gstChoice === 'yes' ? !(gst.gstin && gst.gstin.length === 15) : !profile.declaration)}
              onClick={saveGstStep}
            >Continue →</button>
          </>
        )}

        {step === 2 && (
          <>
            <h3 style={{ marginBottom: 14 }}>KYC verification</h3>
            <div className="form-grid">
              <div className="field">
                <label>Name as per PAN *</label>
                <input className="input" value={kyc.panName || ''} onChange={(e) => setKyc({ ...kyc, panName: e.target.value })} />
              </div>
              <div className="field">
                <label>PAN number *</label>
                <input className="input" value={kyc.panNumber && kyc.panNumber.includes('X') ? '' : kyc.panNumber || ''} onChange={(e) => setKyc({ ...kyc, panNumber: e.target.value.toUpperCase() })} placeholder="ABCDE1234F" maxLength={10} />
                {kyc.panNumber && kyc.panNumber.includes('X') && <div className="help">Saved PAN is masked.</div>}
              </div>
            </div>
            <div className="field">
              <label>PAN document (image)</label>
              <KycUpload onUploaded={(id) => setKyc({ ...kyc, panDocId: id })} />
            </div>
            <div className="field">
              <label>Address proof type</label>
              <select className="select" value={kyc.addressProof || ''} onChange={(e) => setKyc({ ...kyc, addressProof: e.target.value })}>
                <option value="">Select…</option>
                <option value="aadhaar">Aadhaar</option>
                <option value="passport">Passport</option>
                <option value="driving_license">Driving License</option>
                <option value="utility_bill">Utility Bill</option>
              </select>
            </div>
            {kyc.addressProof && (
              <div className="field">
                <label>Address proof document (image)</label>
                <KycUpload onUploaded={(id) => setKyc({ ...kyc, addressDocId: id })} />
              </div>
            )}
            <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
              <button className="btn btn-primary" disabled={busy || !kyc.panName || !kyc.panNumber} onClick={saveKycStep}>Submit for verification →</button>
              {identity.kyc.status !== 'pending' && <span className={'badge ' + (identity.kyc.status === 'verified' ? 'badge-ok' : 'badge-warn')}>Current: {identity.kyc.status}</span>}
            </div>
          </>
        )}

        {step === 3 && (
          <>
            <h3 style={{ marginBottom: 14 }}>Bank / payout details</h3>
            <p className="help" style={{ marginBottom: 14 }}>Payouts go to this account. Numbers are stored securely and always masked in the app.</p>
            <div className="form-grid">
              <div className="field">
                <label>Account holder name *</label>
                <input className="input" value={bank.holderName || ''} onChange={(e) => setBank({ ...bank, holderName: e.target.value })} />
              </div>
              <div className="field">
                <label>Bank name *</label>
                <input className="input" value={bank.bankName || ''} onChange={(e) => setBank({ ...bank, bankName: e.target.value })} />
              </div>
              <div className="field">
                <label>Account number *</label>
                <input className="input" value={(bank as Record<string, unknown>).accountNumberInput as string || ''} onChange={(e) => setBank({ ...bank, accountMasked: undefined, ...({ accountNumberInput: e.target.value } as Partial<BankInfo>) })} placeholder={bank.accountMasked || '9-18 digits'} />
                {bank.accountMasked && <div className="help">Saved: {bank.accountMasked} — type a new number only to change.</div>}
              </div>
              <div className="field">
                <label>IFSC *</label>
                <input className="input" value={bank.ifsc || ''} onChange={(e) => setBank({ ...bank, ifsc: e.target.value.toUpperCase() })} placeholder="SBIN0001234" maxLength={11} />
              </div>
              <div className="field">
                <label>Account type</label>
                <select className="select" value={bank.accountType || 'savings'} onChange={(e) => setBank({ ...bank, accountType: e.target.value })}>
                  <option value="savings">Savings</option>
                  <option value="current">Current</option>
                </select>
              </div>
              <div className="field">
                <label>UPI (optional)</label>
                <input className="input" value={bank.upi || ''} onChange={(e) => setBank({ ...bank, upi: e.target.value })} placeholder="name@okbank" />
              </div>
            </div>
            <button className="btn btn-primary" disabled={busy || !bank.holderName || !bank.bankName || !bank.ifsc} onClick={saveBankStep}>Save payout details →</button>
          </>
        )}

        {step === 4 && (
          <div style={{ textAlign: 'center', padding: '24px 0' }}>
            <div style={{ fontSize: 44, marginBottom: 10 }}>🎉</div>
            <h3>Onboarding complete!</h3>
            <p style={{ color: 'var(--ink-500)', fontSize: 13.5, maxWidth: 460, margin: '8px auto 18px' }}>
              Your Seller ID <strong>{identity.sellerId}</strong> is active. Verification status:{' '}
              <strong>{identity.kyc.status}</strong>. Finish your store profile and add your first product to start selling.
            </p>
            <div style={{ display: 'flex', gap: 10, justifyContent: 'center', flexWrap: 'wrap' }}>
              <Link to="/store" className="btn">Store profile →</Link>
              <Link to="/products/new" className="btn btn-primary">+ Add first product</Link>
            </div>
          </div>
        )}
      </div>

      {identity.eligibility.reasons.length > 0 && step !== 4 && (
        <div className="card card-pad" style={{ marginTop: 18, borderLeft: '4px solid var(--warn-600)' }}>
          <strong style={{ fontSize: 13 }}>Eligibility notes</strong>
          <ul style={{ margin: '8px 0 0 18px', fontSize: 12.5, color: 'var(--ink-500)' }}>
            {identity.eligibility.reasons.map((r, i) => <li key={i}>{r}</li>)}
          </ul>
        </div>
      )}
      {toast.node}
    </div>
  )
}

function KycUpload({ onUploaded }: { onUploaded: (id: number) => void }) {
  const [name, setName] = useState('')
  const [err, setErr] = useState('')
  async function upload(f: File | undefined) {
    if (!f) return
    setErr('')
    try {
      const m = await api.uploadMedia(f)
      onUploaded(m.id)
      setName(f.name)
    } catch (e: unknown) {
      setErr((e as Error).message)
    }
  }
  return (
    <div>
      <label className="btn btn-sm" style={{ cursor: 'pointer' }}>
        {name ? '✓ ' + name : 'Upload document'}
        <input type="file" accept="image/*,.pdf" hidden onChange={(e) => upload(e.target.files?.[0])} />
      </label>
      {err && <div className="help" style={{ color: 'var(--danger-600)' }}>{err}</div>}
    </div>
  )
}

import { useAsync } from '../ui'
function useIdentity() {
  return useAsync(() => api.sellerIdentity(), [])
}
function useOnboardingData() {
  return useAsync(() => api.onboarding(), [])
}
