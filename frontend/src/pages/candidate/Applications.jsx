import React from 'react';
import { Container, Paper, Box, Typography, Chip, Stepper, Step, StepLabel, Button, Divider } from '@mui/material';
import { DataGrid } from '@mui/x-data-grid';
import { Helmet } from 'react-helmet-async';

function CandidateApplications() {
  // TODO: substituir por dados reais da API
  const rows = [
    { id: 1, title: 'Desenvolvedor Full Stack', company: 'TechMoz', status: 'Em análise', updated_at: '2025-10-10' },
    { id: 2, title: 'UI/UX Designer', company: 'Pixel Studio', status: 'Entrevista', updated_at: '2025-10-14' },
    { id: 3, title: 'Analista Financeiro', company: 'Banco MZ', status: 'Rejeitada', updated_at: '2025-10-12' },
  ];

  const columns = [
    { field: 'title', headerName: 'Vaga', flex: 1 },
    { field: 'company', headerName: 'Empresa', width: 200 },
    { field: 'status', headerName: 'Status', width: 180, renderCell: (p) => <Chip label={p.value} color={p.value === 'Rejeitada' ? 'error' : p.value === 'Entrevista' ? 'warning' : 'info'} /> },
    { field: 'updated_at', headerName: 'Atualizado', width: 160 },
  ];

  const steps = ['Submetida', 'Em análise', 'Entrevista', 'Oferta', 'Contratada'];

  return (
    <>
      <Helmet>
        <title>Minhas Candidaturas</title>
      </Helmet>
      <Container maxWidth="lg" sx={{ py: 4 }}>
        <Paper sx={{ p: 3 }}>
          <Typography variant="h5" fontWeight="bold" gutterBottom>
            Minhas Candidaturas
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            Acompanhe o status de cada candidatura
          </Typography>

          <div style={{ height: 420, width: '100%' }}>
            <DataGrid rows={rows} columns={columns} pageSizeOptions={[10, 25, 50]} />
          </div>

          <Divider sx={{ my: 3 }} />

          <Typography variant="h6" gutterBottom>
            Exemplo de Linha do Tempo
          </Typography>
          <Stepper activeStep={2} alternativeLabel>
            {steps.map((label) => (
              <Step key={label}>
                <StepLabel>{label}</StepLabel>
              </Step>
            ))}
          </Stepper>

          <Box sx={{ display: 'flex', justifyContent: 'flex-end', mt: 2 }}>
            <Button variant="outlined">Exportar</Button>
          </Box>
        </Paper>
      </Container>
    </>
  );
}

export default CandidateApplications;
