import React, { useState } from 'react';
import { Container, Paper, Box, Typography, Tabs, Tab, TextField, Button, Chip } from '@mui/material';
import { Helmet } from 'react-helmet-async';
import { DataGrid } from '@mui/x-data-grid';

function CompanyJobs() {
  const [tab, setTab] = useState(0);

  // TODO: trocar por dados vindos da API
  const rows = [
    { id: 1, title: 'Desenvolvedor Full Stack', status: 'Ativa', applications: 12, created_at: '2025-10-01' },
    { id: 2, title: 'Designer UI/UX', status: 'Rascunho', applications: 0, created_at: '2025-10-10' },
  ];

  const columns = [
    { field: 'title', headerName: 'Título', flex: 1 },
    { field: 'status', headerName: 'Estado', width: 150, renderCell: (p) => <Chip label={p.value} color={p.value === 'Ativa' ? 'success' : 'default'} /> },
    { field: 'applications', headerName: 'Candidaturas', width: 150 },
    { field: 'created_at', headerName: 'Criada em', width: 150 },
  ];

  return (
    <>
      <Helmet>
        <title>Minhas Vagas</title>
      </Helmet>
      <Container maxWidth="lg" sx={{ py: 4 }}>
        <Paper sx={{ p: 3 }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
            <Typography variant="h5" fontWeight="bold">Minhas Vagas</Typography>
            <Button variant="contained">Nova Vaga</Button>
          </Box>

          <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 2 }}>
            <Tab label="Ativas" />
            <Tab label="Rascunhos" />
            <Tab label="Arquivadas" />
          </Tabs>

          <div style={{ height: 420, width: '100%' }}>
            <DataGrid rows={rows} columns={columns} pageSizeOptions={[10, 25, 50]} />
          </div>
        </Paper>
      </Container>
    </>
  );
}

export default CompanyJobs;
