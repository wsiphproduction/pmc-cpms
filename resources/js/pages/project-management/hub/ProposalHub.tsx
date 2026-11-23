import { router } from '@inertiajs/react';
import { HubProject, HubShell, InfoStrip, SectionTitle } from './Common';
import { FileHistory, FileVersion, ReplaceFileButton, VersionBadge } from '@/components/FileVersions';

export interface ProposalData {
    url: string | null;
    filename: string | null;
    versions: FileVersion[];
    /** Set on a sub-project with no proposal of its own: it shows its parent's. */
    inherited_from: { id: number; project_no: string } | null;
}

const TONE = '#1d4ed8';

/**
 * Project Proposal (Design Review and Approval) — the approved proposal the
 * project was registered on, with its version history. Uploading here adds a
 * version through the same file slot the Edit Project form uses.
 */
export default function ProposalHub({ project, proposal, canEdit = true }: { project: HubProject; proposal: ProposalData; canEdit?: boolean }) {
    const inherited = proposal.inherited_from;
    const editable  = canEdit && !inherited;

    return (
        <HubShell>
            <InfoStrip project={project} accent={TONE} />
            <SectionTitle color={TONE}>Approved Proposal Document</SectionTitle>

            {inherited && (
                <div style={{ padding: '10px 14px', marginBottom: '14px', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px', fontSize: '12.5px', color: '#475569' }}>
                    This sub-project goes ahead on the proposal approved for its parent,{' '}
                    <button type="button" onClick={() => router.visit(route('projects.hub.proposal', inherited.id))}
                        style={{ padding: 0, border: 'none', background: 'none', color: '#2563eb', fontWeight: 600, cursor: 'pointer', fontSize: 'inherit', textDecoration: 'underline', fontFamily: 'inherit' }}>
                        {inherited.project_no}
                    </button>.
                </div>
            )}

            <div style={{ padding: '16px 18px', border: '1px solid #bfdbfe', borderRadius: '10px', background: '#eff6ff' }}>
                {proposal.url ? (
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                        <a href={proposal.url} target="_blank" rel="noopener noreferrer" style={{ fontSize: '13px', color: '#2563eb', textDecoration: 'none', fontWeight: 600 }}>
                            {proposal.filename ?? 'View / Download Document'} ↗
                        </a>
                        <VersionBadge versions={proposal.versions} tone={TONE} />
                        {editable && (
                            <ReplaceFileButton url={route('files.replace', [project.id, 'proposal', project.id])} tone={TONE} accept=".pdf,.doc,.docx" />
                        )}
                    </div>
                ) : (
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
                        <span style={{ fontSize: '13px', color: '#94a3b8' }}>No proposal document uploaded yet.</span>
                        {editable && (
                            <ReplaceFileButton url={route('files.replace', [project.id, 'proposal', project.id])} tone={TONE} label="Upload" accept=".pdf,.doc,.docx" />
                        )}
                    </div>
                )}
                <FileHistory
                    versions={proposal.versions}
                    tone={TONE}
                    restoreUrl={editable ? v => route('files.restore', [project.id, 'proposal', project.id, v]) : undefined}
                />
            </div>
        </HubShell>
    );
}
